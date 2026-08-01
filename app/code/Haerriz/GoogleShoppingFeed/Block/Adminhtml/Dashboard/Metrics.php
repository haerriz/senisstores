<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Dashboard;

use Haerriz\GoogleShoppingFeed\Model\Conflict\LegacyGoogleFeedDetector;
use Haerriz\GoogleShoppingFeed\Model\FeedRemoteStateRepository;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class Metrics extends Template
{
    private $connection;
    private FeedRemoteStateRepository $remoteStateRepository;
    private LegacyGoogleFeedDetector $legacyDetector;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        FeedRemoteStateRepository $remoteStateRepository,
        LegacyGoogleFeedDetector $legacyDetector,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->connection = $resourceConnection->getConnection();
        $this->remoteStateRepository = $remoteStateRepository;
        $this->legacyDetector = $legacyDetector;
    }

    public function getMetrics()
    {
        $profileTable = $this->connection->getTableName('haerriz_google_shopping_feed_profile');
        $jobTable = $this->connection->getTableName('haerriz_google_shopping_feed_job');
        $logTable = $this->connection->getTableName('haerriz_google_shopping_feed_log');

        $activeProfiles = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$profileTable} WHERE status = 1"
        );

        $totalExported = (int)$this->connection->fetchOne(
            "SELECT SUM(exported_count) FROM {$jobTable} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $failedJobs = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$jobTable} WHERE status = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $recentErrors = [];
        try {
            if ($this->connection->tableColumnExists($logTable, 'type')) {
                $recentErrors = $this->connection->fetchAll(
                    "SELECT message, created_at FROM {$logTable} WHERE type = 'error' ORDER BY log_id DESC LIMIT 5"
                ) ?: [];
            } elseif ($this->connection->tableColumnExists($logTable, 'level')) {
                $recentErrors = $this->connection->fetchAll(
                    "SELECT message, created_at FROM {$logTable} WHERE level = 'error' ORDER BY log_id DESC LIMIT 5"
                ) ?: [];
            }
        } catch (\Throwable $e) {
            $recentErrors = [];
        }

        $totalJobs = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$jobTable} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $successRate = $totalJobs > 0 ? round((($totalJobs - $failedJobs) / $totalJobs) * 100, 1) : 100;

        $merchantCounts = [
            'approved' => 0,
            'disapproved' => 0,
            'pending' => 0,
        ];
        $recentDisapproved = [];
        try {
            $statusCounts = $this->remoteStateRepository->getStatusCounts();
            $merchantCounts['approved'] = (int)($statusCounts['approved'] ?? 0);
            $merchantCounts['disapproved'] = (int)($statusCounts['disapproved'] ?? 0);
            $merchantCounts['pending'] = (int)($statusCounts['pending'] ?? 0);
            $recentDisapproved = $this->remoteStateRepository->getRecentDisapproved(10);
        } catch (\Throwable $e) {
            // Remote state table may not be present yet during upgrades.
        }

        return [
            'active_profiles' => $activeProfiles,
            'total_exported' => $totalExported,
            'failed_jobs' => $failedJobs,
            'success_rate' => $successRate,
            'recent_errors' => $recentErrors,
            'merchant_approved' => $merchantCounts['approved'],
            'merchant_disapproved' => $merchantCounts['disapproved'],
            'merchant_pending' => $merchantCounts['pending'],
            'merchant_tracked' => array_sum(array_map('intval', $merchantCounts)),
            'recent_disapproved' => $recentDisapproved,
            'legacy_conflict' => $this->legacyDetector->isConflictDetected(),
            'legacy_conflict_message' => $this->legacyDetector->getWarningMessage(),
        ];
    }

    public function getReconcileUrl(): string
    {
        return $this->getUrl('haerriz_googleshoppingfeed/dashboard/reconcile');
    }

    public function getAdminFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
