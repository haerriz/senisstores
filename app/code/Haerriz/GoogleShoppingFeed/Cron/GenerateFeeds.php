<?php
namespace Haerriz\GoogleShoppingFeed\Cron;

use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Psr\Log\LoggerInterface;

class GenerateFeeds
{
    protected $collectionFactory;
    protected $feedGenerator;
    protected $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        FeedGenerator $feedGenerator,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->feedGenerator = $feedGenerator;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->info("Cron job haerriz_google_shopping_feed_generate is executed.");
        
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', 1);

        foreach ($collection as $profile) {
            try {
                $this->logger->info("Generating feed profile ID: " . $profile->getId());
                $this->feedGenerator->generate($profile);
                $this->logger->info("Successfully generated feed profile ID: " . $profile->getId());
            } catch (\Exception $e) {
                $this->logger->error("Error generating feed profile ID: " . $profile->getId() . " - " . $e->getMessage());
            }
        }
        
        return $this;
    }
}
