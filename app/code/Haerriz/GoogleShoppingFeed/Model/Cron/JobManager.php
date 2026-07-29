<?php
namespace Haerriz\GoogleShoppingFeed\Model\Cron;

use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob\CollectionFactory as JobCollectionFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Magento\Framework\Stdlib\DateTime\DateTime;

class JobManager
{
    /**
     * @var JobCollectionFactory
     */
    protected $jobCollectionFactory;

    /**
     * @var FeedJobRepositoryInterface
     */
    protected $repository;

    /**
     * @var FeedGenerator
     */
    protected $generator;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @param JobCollectionFactory $jobCollectionFactory
     * @param FeedJobRepositoryInterface $repository
     * @param FeedGenerator $generator
     * @param DateTime $date
     */
    public function __construct(
        JobCollectionFactory $jobCollectionFactory,
        FeedJobRepositoryInterface $repository,
        FeedGenerator $generator,
        DateTime $date
    ) {
        $this->jobCollectionFactory = $jobCollectionFactory;
        $this->repository = $repository;
        $this->generator = $generator;
        $this->date = $date;
    }

    /**
     * Cancel running or pending job
     *
     * @param int $jobId
     * @return bool
     */
    public function cancelJob($jobId)
    {
        try {
            $job = $this->repository->getById($jobId);
            if ($job->getStatus() === 'running' || $job->getStatus() === 'pending') {
                $job->setStatus('cancelled');
                $job->setFinishedAt($this->date->gmtDate());
                $this->repository->save($job);
                return true;
            }
        } catch (\Exception $e) {
            // silently absorb
        }
        return false;
    }

    /**
     * Perform retention cleanups safely
     *
     * @param int $retentionDays
     * @return void
     */
    public function cleanupOldJobs($retentionDays = 30)
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            $collection = $this->jobCollectionFactory->create();
            $collection->addFieldToFilter('created_at', ['lt' => $cutoffDate]);
            
            // Collect latest successful job per profile to avoid deleting them
            $latestSuccessfulIds = [];
            $successCollection = $this->jobCollectionFactory->create();
            $successCollection->addFieldToFilter('status', 'success');
            foreach ($successCollection as $job) {
                $profileId = $job->getProfileId();
                if (!isset($latestSuccessfulIds[$profileId]) || $job->getId() > $latestSuccessfulIds[$profileId]) {
                    $latestSuccessfulIds[$profileId] = $job->getId();
                }
            }

            foreach ($collection as $job) {
                // Do not delete active/latest-successful entries
                if ($job->getStatus() === 'running') {
                    continue;
                }
                if (in_array($job->getId(), $latestSuccessfulIds)) {
                    continue;
                }
                $this->repository->delete($job);
            }
        } catch (\Exception $e) {
            // absorption
        }
    }
}
