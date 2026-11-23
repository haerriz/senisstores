<?php

declare(strict_types=1);

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;

require dirname(__DIR__) . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$storeManager = $objectManager->get(StoreManagerInterface::class);
$store = $storeManager->getDefaultStoreView();
$storeId = (int) $store->getId();

$categoryCollection = $objectManager->get(CategoryCollectionFactory::class)->create();
$categoryCollection->setStoreId($storeId)
    ->addAttributeToSelect(['name', 'description', 'meta_title', 'meta_description', 'url_key', 'is_active'])
    ->addAttributeToFilter('is_active', 1)
    ->addFieldToFilter('level', ['gt' => 1])
    ->addOrderField('path');

$productCollectionFactory = $objectManager->get(ProductCollectionFactory::class);
$rows = [];
foreach ($categoryCollection as $category) {
    $products = $productCollectionFactory->create();
    $products->setStoreId($storeId)
        ->addAttributeToSelect([
            'name', 'price', 'special_price', 'status', 'visibility',
            'description', 'short_description', 'meta_title', 'meta_description'
        ])
        ->addCategoriesFilter(['in' => [(int) $category->getId()]])
        ->addAttributeToFilter('status', 1)
        ->setPageSize(20);

    $productRows = [];
    foreach ($products as $product) {
        $productRows[] = [
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'price' => (float) $product->getPrice(),
            'description' => trim(strip_tags((string) $product->getDescription())),
            'short_description' => trim(strip_tags((string) $product->getShortDescription())),
            'meta_title' => trim((string) $product->getMetaTitle()),
            'meta_description' => trim((string) $product->getMetaDescription()),
        ];
    }

    $rows[] = [
        'id' => (int) $category->getId(),
        'name' => (string) $category->getName(),
        'url' => (string) $category->getUrl(),
        'description' => trim(strip_tags((string) $category->getDescription())),
        'meta_title' => trim((string) $category->getMetaTitle()),
        'meta_description' => trim((string) $category->getMetaDescription()),
        'updated_at' => (string) $category->getUpdatedAt(),
        'products' => $productRows,
    ];
}

echo json_encode(
    ['store_id' => $storeId, 'categories' => $rows],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
) . PHP_EOL;
