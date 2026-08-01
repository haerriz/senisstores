<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Haerriz\GoogleShoppingFeed\Model\Config;
use Haerriz\GoogleShoppingFeed\Model\FeedRemoteStateRepository;
use Psr\Log\LoggerInterface;

class StatusReconciliation
{
    private $merchantClient;
    private $config;
    private $remoteStateRepo;
    private $logger;

    public function __construct(
        MerchantClientV1 $merchantClient,
        Config $config,
        FeedRemoteStateRepository $remoteStateRepo,
        LoggerInterface $logger
    ) {
        $this->merchantClient  = $merchantClient;
        $this->config          = $config;
        $this->remoteStateRepo = $remoteStateRepo;
        $this->logger          = $logger;
    }

    /**
     * Pull latest approval statuses from Google Merchant Center
     * and update local haerriz_google_shopping_feed_remote_state table.
     */
    public function reconcile(int $storeId = 0): array
    {
        $merchantId = $this->config->getMerchantId($storeId);

        if (!$merchantId) {
            return [
                'reconciled' => false,
                'reason' => 'no_merchant_id',
                'message' => 'Google Merchant Account ID is not configured.',
            ];
        }

        if (!$this->config->getServiceAccountJson($storeId)) {
            return [
                'reconciled' => false,
                'reason' => 'missing_credentials',
                'message' => 'Google Merchant API service account JSON is not configured.',
            ];
        }

        $reconciled = 0;
        $statuses   = [];

        try {
            $remoteProducts = $this->merchantClient->listProducts($merchantId);

            foreach ($remoteProducts as $remoteProduct) {
                $sku    = (string)($remoteProduct['offerId'] ?? $remoteProduct['offer_id'] ?? '');
                if ($sku === '') {
                    continue;
                }
                $status = $this->normalizeStatus($remoteProduct);
                $issues = $remoteProduct['itemLevelIssues'] ?? [];

                // Persist to local state table (profile_id=0 = merchant-account level snapshot)
                try {
                    $state = $this->remoteStateRepo->getByOfferIdAndProfile($sku, null);
                    $state->setProfileId(null);
                    $state->setRemoteStatus($status);
                    $state->setIssues(json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $state->setSyncedAt(date('Y-m-d H:i:s'));
                    $this->remoteStateRepo->save($state);
                    $reconciled++;
                } catch (\Throwable $innerEx) {
                    $this->logger->debug("StatusReconciliation: SKU [{$sku}] state update failed: " . $innerEx->getMessage());
                }

                $statuses[$sku] = $status;
            }

        } catch (\Throwable $e) {
            $this->logger->error("StatusReconciliation::reconcile failed: " . $e->getMessage());
            return ['reconciled' => false, 'error' => $e->getMessage()];
        }

        return ['reconciled' => true, 'count' => $reconciled, 'statuses' => $statuses];
    }

    private function normalizeStatus(array $remoteProduct): string
    {
        $issues = $remoteProduct['itemLevelIssues'] ?? [];
        $raw = strtolower((string)(
            $remoteProduct['status']
            ?? $remoteProduct['approvalStatus']
            ?? $remoteProduct['destinationStatuses'][0]['status']
            ?? ''
        ));

        if (str_contains($raw, 'disapproved') || str_contains($raw, 'rejected')) {
            return 'disapproved';
        }
        if (str_contains($raw, 'approved') || str_contains($raw, 'eligible') || str_contains($raw, 'serving')) {
            return 'approved';
        }
        if (str_contains($raw, 'pending') || str_contains($raw, 'review')) {
            return 'pending';
        }

        if (is_array($issues) && count($issues) > 0) {
            return 'disapproved';
        }

        return 'pending';
    }
}
