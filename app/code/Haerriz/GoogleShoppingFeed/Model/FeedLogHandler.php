<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Model\FeedLogFactory;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog as LogResource;

class FeedLogHandler
{
    /**
     * @var FeedLogFactory
     */
    protected $logFactory;

    /**
     * @var LogResource
     */
    protected $resource;

    /**
     * @param FeedLogFactory $logFactory
     * @param LogResource $resource
     */
    public function __construct(FeedLogFactory $logFactory, LogResource $resource)
    {
        $this->logFactory = $logFactory;
        $this->resource = $resource;
    }

    /**
     * Log message in database logs table
     *
     * @param int|null $profileId
     * @param int|null $jobId
     * @param string $type
     * @param string $message
     * @return void
     */
    public function log($profileId, $jobId, $type, $message)
    {
        try {
            $log = $this->logFactory->create();
            $log->setData([
                'profile_id' => $profileId,
                'job_id' => $jobId,
                'type' => $type,
                'message' => $message
            ]);
            $this->resource->save($log);
        } catch (\Exception $e) {
            // Silently absorb logger errors to avoid stopping job flows
        }
    }
}
