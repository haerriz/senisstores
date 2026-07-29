<?php
namespace Haerriz\GoogleShoppingFeed\Cron;

use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Haerriz\GoogleShoppingFeed\Model\Api\MerchantClient;
use Psr\Log\LoggerInterface;

class GenerateFeeds
{
    protected $collectionFactory;
    protected $feedGenerator;
    protected $merchantClient;
    protected $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        FeedGenerator $feedGenerator,
        MerchantClient $merchantClient,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->feedGenerator = $feedGenerator;
        $this->merchantClient = $merchantClient;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->info("Starting Google Shopping Feed Generation via Cron");

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', 1); // Only active profiles

        foreach ($collection as $profile) {
            $this->logger->info("Generating feed for profile ID: " . $profile->getId());
            
            try {
                // Generate the XML or CSV locally
                $this->feedGenerator->generate($profile);
                $this->logger->info("Feed file generated: " . $profile->getFilename());
                
                // If the feed is specifically XML for Google, we can also push individual products
                // via API if requested. For this integration, we'll demonstrate pushing a heartbeat/sync
                // to the Merchant API.
                
                // Fetch the array of generated products from generator if we were inserting item-by-item:
                // $products = $this->feedGenerator->getGeneratedProductsArray();
                // foreach ($products as $prodData) {
                //    $this->merchantClient->insertProduct($prodData);
                // }

                $this->merchantClient->insertProduct(['dummy_sync' => true, 'profile_id' => $profile->getId()]);
                
            } catch (\Exception $e) {
                $this->logger->error("Error generating feed profile {$profile->getId()}: " . $e->getMessage());
            }
        }

        $this->logger->info("Completed Google Shopping Feed Generation via Cron");
        return $this;
    }
}
