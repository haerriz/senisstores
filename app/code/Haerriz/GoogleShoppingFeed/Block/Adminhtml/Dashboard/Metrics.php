<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Dashboard;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class Metrics extends Template
{
    private $connection;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->connection = $resourceConnection->getConnection();
    }

    public function getMetrics()
    {
        $profileTable = $this->connection->getTableName('haerriz_google_shopping_feed_profile');
        $jobTable = $this->connection->getTableName('haerriz_google_shopping_feed_job');
        $logTable = $this->connection->getTableName('haerriz_google_shopping_feed_log');

        // 1. Active Profiles Count
        $activeProfiles = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$profileTable} WHERE status = 1"
        );

        // 2. Total Exported (last 30 days)
        $totalExported = (int)$this->connection->fetchOne(
            "SELECT SUM(exported_count) FROM {$jobTable} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // 3. Failed Jobs (last 30 days)
        $failedJobs = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$jobTable} WHERE status = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // 4. Recent Errors
        $recentErrors = $this->connection->fetchAll(
            "SELECT message, created_at FROM {$logTable} WHERE type = 'error' ORDER BY log_id DESC LIMIT 5"
        );

        // 5. Success Rate
        $totalJobs = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$jobTable} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $successRate = $totalJobs > 0 ? round((($totalJobs - $failedJobs) / $totalJobs) * 100, 1) : 100;

        return [
            'active_profiles' => $activeProfiles,
            'total_exported'  => $totalExported,
            'failed_jobs'     => $failedJobs,
            'success_rate'    => $successRate,
            'recent_errors'   => $recentErrors
        ];
    }
}
