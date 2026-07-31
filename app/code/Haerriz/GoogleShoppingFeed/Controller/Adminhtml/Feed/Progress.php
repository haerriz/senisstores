<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

class Progress extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $jsonFactory;
    private $connection;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->connection  = $resourceConnection->getConnection();
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $profileId = (int)$this->getRequest()->getParam('id');
        $lastLogId = (int)$this->getRequest()->getParam('last_log_id', 0);

        if (!$profileId) {
            return $result->setData(['error' => 'No profile ID']);
        }

        $jobTable = $this->connection->getTableName('haerriz_google_shopping_feed_job');
        $logTable = $this->connection->getTableName('haerriz_google_shopping_feed_log');

        // Get the latest job for this profile
        $job = $this->connection->fetchRow(
            "SELECT * FROM {$jobTable} WHERE profile_id = :profile_id ORDER BY job_id DESC LIMIT 1",
            [':profile_id' => $profileId]
        );

        if (!$job) {
            return $result->setData(['status' => 'waiting']);
        }

        $jobId = (int)$job['job_id'];
        
        // Get logs since last log id
        $logs = $this->connection->fetchAll(
            "SELECT log_id, level, message, created_at FROM {$logTable} WHERE job_id = :job_id AND log_id > :last_log_id ORDER BY log_id ASC LIMIT 100",
            [':job_id' => $jobId, ':last_log_id' => $lastLogId]
        );

        return $result->setData([
            'status'             => $job['status'],
            'total_products'     => (int)$job['total_products'],
            'processed_products' => (int)$job['processed_products'],
            'exported_count'     => (int)$job['exported_count'],
            'error_count'        => (int)$job['error_count'],
            'failure_message'    => $job['failure_message'],
            'logs'               => $logs
        ]);
    }
}
