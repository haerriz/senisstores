<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Haerriz\GoogleShoppingFeed\Model\Config;
use Psr\Log\LoggerInterface;

class ProductSynchronizer
{
    private $merchantClient;
    private $config;
    private $logger;

    public function __construct(
        MerchantClientV1 $merchantClient,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->merchantClient = $merchantClient;
        $this->config         = $config;
        $this->logger         = $logger;
    }

    /**
     * Synchronize products with Google Merchant Center.
     * Batches inserts/updates to avoid API rate limits.
     */
    public function sync(array $products, int $storeId = 0): array
    {
        $merchantId = $this->config->getMerchantId($storeId);

        if (!$merchantId) {
            $this->logger->warning("ProductSynchronizer: No Merchant ID configured for store {$storeId}");
            return ['synced' => 0, 'status' => 'skipped', 'reason' => 'no_merchant_id'];
        }
        if (!$this->config->getServiceAccountJson($storeId)) {
            $this->logger->warning("ProductSynchronizer: Merchant API credentials are not configured for store {$storeId}");
            return ['synced' => 0, 'status' => 'skipped', 'reason' => 'missing_credentials'];
        }

        $synced  = 0;
        $errors  = [];
        $batches = array_chunk($products, 50); // Google Merchant API max batch = 50

        foreach ($batches as $batch) {
            try {
                $result = $this->merchantClient->batchInsertProducts($merchantId, $batch);
                $synced += $result['inserted'] ?? count($batch);
            } catch (\Exception $e) {
                $this->logger->error("ProductSynchronizer batch failed: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'status' => empty($errors) ? 'success' : 'partial',
        ];
    }
}
