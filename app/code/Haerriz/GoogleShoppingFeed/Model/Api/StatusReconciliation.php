<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Haerriz\GoogleShoppingFeed\Model\Api\MerchantClientV1;
use Haerriz\GoogleShoppingFeed\Model\FeedLogHandler;

class StatusReconciliation
{
    /**
     * @var MerchantClientV1
     */
    protected $clientV1;

    /**
     * @var FeedLogHandler
     */
    protected $logHandler;

    /**
     * @param MerchantClientV1 $clientV1
     * @param FeedLogHandler $logHandler
     */
    public function __construct(
        MerchantClientV1 $clientV1,
        FeedLogHandler $logHandler
    ) {
        $this->clientV1 = $clientV1;
        $this->logHandler = $logHandler;
    }

    /**
     * Reconcile approving warnings or disapprovals for synchronized SKU
     *
     * @param string $sku
     * @param int $profileId
     * @return void
     */
    public function reconcileProductStatus($sku, $profileId)
    {
        try {
            $client = $this->clientV1->getProductsClient();
            $name = sprintf(
                'accounts/%s/products/%s~en~US~%s',
                $this->clientV1->getMerchantId(),
                'online',
                $sku
            );

            $product = $client->getProduct($name);
            $itemStatus = $product->getItemStatus();
            
            if ($itemStatus) {
                foreach ($itemStatus->getIssues() as $issue) {
                    $message = sprintf(
                        "MC Issue for SKU %s - Code: %s. Severity: %s. Description: %s. Field: %s",
                        $sku,
                        $issue->getCode(),
                        $issue->getSeverity(),
                        $issue->getDescription(),
                        $issue->getAttribute()
                    );
                    $this->logHandler->log($profileId, null, 'warning', $message);
                }
            }
        } catch (\Exception $e) {
            // absorption for missing / not yet fully compiled MC records
        }
    }
}
