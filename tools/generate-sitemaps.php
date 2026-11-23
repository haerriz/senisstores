<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory;
use Magento\Sitemap\Model\SitemapFactory;
use Magento\Store\Model\StoreManagerInterface;

require dirname(__DIR__) . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(State::class)->setAreaCode('frontend');
$collection = $objectManager->get(CollectionFactory::class)->create();
$initialSize = (int) $collection->getSize();

if ($initialSize === 0) {
    $store = $objectManager->get(StoreManagerInterface::class)->getDefaultStoreView();
    $sitemap = $objectManager->get(SitemapFactory::class)->create();
    $sitemap->setSitemapFilename('sitemap.xml')
        ->setSitemapPath('/')
        ->setStoreId((int) $store->getId())
        ->save();
    $collection = $objectManager->get(CollectionFactory::class)->create();
}

$collection->load();
if ($collection->getSize() === 0) {
    fwrite(STDERR, "Unable to create or load the sitemap record.\n");
    exit(1);
}

printf("Loaded %d configured sitemap record(s).\n", (int) $collection->getSize());

foreach ($collection as $sitemap) {
    $sitemap->generateXml();
    printf(
        "Generated sitemap %d for store %d at %s%s\n",
        (int) $sitemap->getId(),
        (int) $sitemap->getStoreId(),
        (string) $sitemap->getSitemapPath(),
        (string) $sitemap->getSitemapFilename()
    );
}
