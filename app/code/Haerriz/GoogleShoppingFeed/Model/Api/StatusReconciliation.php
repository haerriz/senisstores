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
            return ['reconciled' => false, 'reason' => 'no_merchant_id'];
        }

        $reconciled = 0;
        $statuses   = [];

        try {
            $remoteProducts = $this->merchantClient->listProducts($merchantId);

            foreach ($remoteProducts as $remoteProduct) {
                $sku    = $remoteProduct['offerId']   ?? '';
                $status = $remoteProduct['status']    ?? 'unknown';
                $issues = $remoteProduct['itemLevelIssues'] ?? [];

                // Persist to local state table
                try {
                    $state = $this->remoteStateRepo->getByOfferIdAndProfile($sku, $merchantId);
                    $state->setRemoteStatus($status);
                    $state->setIssues(json_encode($issues));
                    $state->setSyncedAt(date('Y-m-d H:i:s'));
                    $this->remoteStateRepo->save($state);
                    $reconciled++;
                } catch (\Exception $innerEx) {
                    $this->logger->debug("StatusReconciliation: SKU [{$sku}] state update failed: " . $innerEx->getMessage());
                }

                $statuses[$sku] = $status;
            }

        } catch (\Exception $e) {
            $this->logger->error("StatusReconciliation::reconcile failed: " . $e->getMessage());
            return ['reconciled' => false, 'error' => $e->getMessage()];
        }

        return ['reconciled' => true, 'count' => $reconciled, 'statuses' => $statuses];
    }
}
