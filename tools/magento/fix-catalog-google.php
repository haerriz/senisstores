<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
require '/home/u434561653/domains/senisstores.com/public_html/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$obj->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

/** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
$productRepository = $obj->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
/** @var \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry */
$stockRegistry = $obj->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);

$fixes = [
    'Garden scissors' => ['weight' => 0.2],
    'PVC Wire' => ['qty' => 100],
];

foreach ($fixes as $sku => $data) {
    try {
        $product = $productRepository->get($sku, false, 0, true);

        if (isset($data['weight'])) {
            $product->setWeight($data['weight']);
            $productRepository->save($product);
            echo "Updated weight for {$sku}: {$data['weight']} kg\n";
        }

        if (isset($data['qty'])) {
            $stockItem = $stockRegistry->getStockItem((int) $product->getId());
            $stockItem->setQty($data['qty']);
            $stockItem->setIsInStock(1);
            $stockRegistry->updateStockItemBySku($sku, $stockItem);
            echo "Updated stock for {$sku}: qty {$data['qty']}, in stock\n";
        }
    } catch (\Exception $e) {
        echo "ERROR {$sku}: {$e->getMessage()}\n";
    }
}

echo "Done.\n";
