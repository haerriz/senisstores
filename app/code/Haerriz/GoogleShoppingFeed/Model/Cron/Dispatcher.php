<?php
namespace Haerriz\GoogleShoppingFeed\Model\Cron;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedExporter;
use Haerriz\GoogleShoppingFeed\Model\FeedJobRepository;
use Haerriz\GoogleShoppingFeed\Model\FeedJobFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class Dispatcher
{
    private $repository;
    private $searchCriteriaBuilder;
    private $exporter;
    private $scheduler;
    private $jobRepository;
    private $jobFactory;
    private $logger;

    public function __construct(
        FeedProfileRepositoryInterface $repository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FeedExporter $exporter,
        Scheduler $scheduler,
        FeedJobRepository $jobRepository,
        FeedJobFactory $jobFactory,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->exporter = $exporter;
        $this->scheduler = $scheduler;
        $this->jobRepository = $jobRepository;
        $this->jobFactory = $jobFactory;
        $this->logger = $logger;
    }

    public function dispatch()
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('status', 1)
            ->create();

        $profiles = $this->repository->getList($searchCriteria)->getItems();
        $dispatched = 0;

        foreach ($profiles as $profile) {
            if (!$this->scheduler->isDue($profile)) {
                $this->logger->debug(sprintf(
                    'GoogleShoppingFeed: Profile #%d "%s" is not due yet, skipping.',
                    $profile->getId(),
                    $profile->getName()
                ));
                continue;
            }

            try {
                $outputPath = 'pub/media/' . $profile->getFilename();
                $job = $this->jobFactory->create();
                $job->setProfileId($profile->getId());
                $job->setTriggerSource('cron');
                $job->setStatus('running');
                $job->setStartedAt(date('Y-m-d H:i:s'));
                $this->jobRepository->save($job);

                $result = $this->exporter->export($profile, $outputPath, $job);

                $job->setStatus('done');
                $job->setFinishedAt(date('Y-m-d H:i:s'));
                $this->jobRepository->save($job);

                $this->logger->info(sprintf(
                    'GoogleShoppingFeed: Profile #%d "%s" exported %d products.',
                    $profile->getId(),
                    $profile->getName(),
                    $result['exported'] ?? 0
                ));
                $dispatched++;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'GoogleShoppingFeed: Profile #%d "%s" failed: %s',
                    $profile->getId(),
                    $profile->getName(),
                    $e->getMessage()
                ));
                if (isset($job)) {
                    $job->setStatus('error');
                    $job->setFinishedAt(date('Y-m-d H:i:s'));
                    $this->jobRepository->save($job);
                }
            }
        }

        return $dispatched;
    }
}
