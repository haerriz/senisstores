<?php
namespace Haerriz\GoogleShoppingFeed\Model\Generation;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\GenerationResultInterface;
use Haerriz\GoogleShoppingFeed\Api\GenerationOrchestratorInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedExporter;
use Haerriz\GoogleShoppingFeed\Model\FeedJobFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedJobRepository;
use Haerriz\GoogleShoppingFeed\Model\FeedLogHandler;
use Psr\Log\LoggerInterface;

class Orchestrator implements GenerationOrchestratorInterface
{
    private FeedExporter $exporter;
    private FeedJobFactory $jobFactory;
    private FeedJobRepository $jobRepository;
    private FailureClassifier $failureClassifier;
    private ProfileLock $lock;
    private ProfileSnapshot $snapshot;
    private FeedLogHandler $feedLogHandler;
    private LoggerInterface $logger;

    public function __construct(
        FeedExporter $exporter,
        FeedJobFactory $jobFactory,
        FeedJobRepository $jobRepository,
        FailureClassifier $failureClassifier,
        ProfileLock $lock,
        ProfileSnapshot $snapshot,
        FeedLogHandler $feedLogHandler,
        LoggerInterface $logger
    ) {
        $this->exporter = $exporter;
        $this->jobFactory = $jobFactory;
        $this->jobRepository = $jobRepository;
        $this->failureClassifier = $failureClassifier;
        $this->lock = $lock;
        $this->snapshot = $snapshot;
        $this->feedLogHandler = $feedLogHandler;
        $this->logger = $logger;
    }

    public function generate(FeedProfileInterface $profile, string $trigger = 'manual'): GenerationResultInterface
    {
        try {
            $result = $this->run($profile, $trigger);
            if (!empty($result['skipped'])) {
                return new GenerationResult(
                    false,
                    0,
                    0,
                    (string)($result['reason'] ?? 'skipped')
                );
            }

            return new GenerationResult(
                true,
                (int)($result['job_id'] ?? 0),
                (int)($result['exported'] ?? 0)
            );
        } catch (\Throwable $e) {
            return new GenerationResult(false, 0, 0, $e->getMessage());
        }
    }

    public function run(FeedProfileInterface $profile, string $triggerSource = 'manual'): array
    {
        $profileId = (int)$profile->getId();

        if (!$this->lock->acquire($profileId)) {
            $this->logger->warning("Orchestrator: Profile #{$profileId} already running — skipping.");
            return ['skipped' => true, 'reason' => 'locked'];
        }

        $snapshotData = [];
        try {
            $snapshotData = $this->snapshot->take($profile);
            $this->logger->debug(
                "Orchestrator: Snapshot taken for profile #{$profileId}: " . json_encode($snapshotData)
            );
        } catch (\Exception $e) {
            $this->logger->debug('Orchestrator: Snapshot failed (non-fatal): ' . $e->getMessage());
        }

        $job = $this->jobFactory->create();
        $job->setProfileId($profileId);
        $job->setTriggerSource($triggerSource);
        $job->setStatus('running');
        $job->setStartedAt(date('Y-m-d H:i:s'));
        $job->setData('snapshot', json_encode($snapshotData));
        $this->jobRepository->save($job);

        $this->feedLogHandler->log(
            $job,
            'info',
            "Job #{$job->getId()} created for profile [{$profile->getName()}], trigger={$triggerSource}"
        );

        try {
            $filename = preg_replace('#^pub/media/#', '', ltrim((string)$profile->getFilename(), '/'));
            $result = $this->exporter->export($profile, $filename, $job);

            $job->setStatus('done');
            $job->setFinishedAt(date('Y-m-d H:i:s'));
            $this->jobRepository->save($job);

            $this->feedLogHandler->log(
                $job,
                'info',
                "Job #{$job->getId()} completed. exported={$result['exported']}, size={$result['fileSize']}B"
            );
            $this->logger->info(
                "Orchestrator: Profile #{$profileId} [{$profile->getName()}] done. exported={$result['exported']}"
            );

            $result['job_id'] = (int)$job->getId();
            return $result;
        } catch (\Exception $e) {
            $category = $this->failureClassifier->classify($e);

            $job->setStatus('error');
            $job->setFinishedAt(date('Y-m-d H:i:s'));
            $job->setData('failure_category', $category);
            $job->setData('failure_message', $e->getMessage());
            $this->jobRepository->save($job);

            $this->feedLogHandler->log(
                $job,
                'error',
                "Job #{$job->getId()} FAILED [{$category}]: " . $e->getMessage()
            );
            $this->logger->error(
                "Orchestrator: Profile #{$profileId} FAILED [{$category}]: " . $e->getMessage()
            );

            throw $e;
        } finally {
            $this->lock->release($profileId);
            $this->feedLogHandler->log($job ?? null, 'debug', "Lock released for profile #{$profileId}");
        }
    }
}
