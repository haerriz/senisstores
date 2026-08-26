<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Inventory;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Storefront-safe inventory view.
 *
 * The module supports Magento's legacy CatalogInventory API everywhere and, when MSI is active,
 * resolves the website stock and uses InventorySalesApi for salable quantity/status. MSI is
 * intentionally discovered at runtime so this module can still install on stores where inventory
 * modules have been disabled while keeping one core package.
 */
class InventoryService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private StockRegistryInterface $stockRegistry,
        private StoreManagerInterface $storeManager,
        private ModuleManager $moduleManager,
        private Config $config,
        private LoggerInterface $logger
    ) {
    }

    public function get(string $sku, ?int $storeId = null, float $requestedQty = 1.0): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new LocalizedException(__('A product SKU is required.'));
        }
        $store = $this->storeManager->getStore($storeId);
        $product = $this->products->get($sku, false, (int)$store->getId(), true);
        return $this->getForProduct($product, (int)$store->getId(), $requestedQty);
    }

    /**
     * Batch storefront availability for a bounded SKU list.
     *
     * @param string[] $skus
     * @return array<int,array<string,mixed>>
     */
    public function getMany(array $skus, ?int $storeId = null, float $requestedQty = 1.0, int $limit = 24): array
    {
        $out = [];
        $seen = [];
        foreach (array_slice($skus, 0, max(1, min(24, $limit))) as $sku) {
            $sku = trim((string)$sku);
            $key = mb_strtolower($sku);
            if ($sku === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            try {
                $out[] = $this->get($sku, $storeId, $requestedQty);
            } catch (\Throwable $e) {
                $this->logger->debug('Agentic inventory batch lookup skipped SKU ' . $sku, ['exception' => $e]);
            }
        }
        return $out;
    }

    public function getForProduct(ProductInterface $product, ?int $storeId = null, float $requestedQty = 1.0): array
    {
        $store = $this->storeManager->getStore($storeId ?: (int)$product->getStoreId());
        $sku = (string)$product->getSku();
        $websiteId = (int)$store->getWebsiteId();
        $stockItem = $this->stockRegistry->getStockItem((int)$product->getId(), $websiteId);

        $manageStock = (bool)$stockItem->getManageStock();
        $legacyQty = $manageStock ? (float)$stockItem->getQty() : null;
        $backorders = (int)$stockItem->getBackorders();
        $salable = (bool)$product->isSalable();
        $salableQty = null;
        $quantitySource = $manageStock ? 'catalog_inventory' : 'not_managed';
        $requestedSalable = $salable;
        $stockId = null;

        $msi = $this->msiAvailability($sku, (string)$store->getWebsite()->getCode(), $requestedQty);
        if ($msi !== null) {
            $stockId = $msi['stock_id'];
            $salable = $msi['salable'];
            $requestedSalable = $msi['requested_salable'];
            $salableQty = $msi['salable_qty'];
            $quantitySource = 'msi_salable';
        } elseif ($manageStock) {
            $salableQty = $legacyQty;
            $requestedSalable = $salable && ($legacyQty === null || $legacyQty >= $requestedQty || $backorders > 0);
        }

        $threshold = $this->config->getInventoryLowStockThreshold((int)$store->getId());
        $publicQty = $this->config->isInventoryQuantityExposed((int)$store->getId()) ? $salableQty : null;
        $minSaleQty = max(0.0, (float)$stockItem->getMinSaleQty());
        $maxSaleQty = max(0.0, (float)$stockItem->getMaxSaleQty());
        $qtyIncrements = max(0.0, (float)$stockItem->getQtyIncrements());
        $requestedQty = max(0.0001, $requestedQty);
        $meetsMinimum = $minSaleQty <= 0.0 || $requestedQty >= $minSaleQty;
        $meetsMaximum = $maxSaleQty <= 0.0 || $requestedQty <= $maxSaleQty;
        $meetsIncrement = $qtyIncrements <= 0.0 || abs(($requestedQty / $qtyIncrements) - round($requestedQty / $qtyIncrements)) < 0.00001;
        $requestedSalable = $requestedSalable && $meetsMinimum && $meetsMaximum && $meetsIncrement;
        $lowStock = $salable && $salableQty !== null && $salableQty > 0 && $salableQty <= $threshold;
        $backorderable = $salable && $salableQty !== null && $salableQty <= 0 && $backorders > 0;
        $message = $this->availabilityMessage(
            $salable,
            $publicQty,
            $lowStock,
            $backorderable,
            $manageStock,
            $requestedQty,
            $requestedSalable,
            $meetsMinimum,
            $meetsMaximum,
            $meetsIncrement,
            $minSaleQty,
            $maxSaleQty,
            $qtyIncrements
        );

        return [
            'sku' => (string)$product->getSku(),
            'product_id' => (int)$product->getId(),
            'product_type' => (string)$product->getTypeId(),
            'is_salable' => $salable,
            'requested_qty' => $requestedQty,
            'requested_qty_salable' => $requestedSalable,
            'meets_min_sale_qty' => $meetsMinimum,
            'meets_max_sale_qty' => $meetsMaximum,
            'meets_qty_increment' => $meetsIncrement,
            'salable_qty' => $publicQty,
            'quantity_exposed' => $publicQty !== null,
            'quantity_source' => $quantitySource,
            'stock_id' => $stockId,
            'manage_stock' => $manageStock,
            'backorders' => $backorders,
            'backorderable' => $backorderable,
            'min_sale_qty' => $minSaleQty,
            'max_sale_qty' => $maxSaleQty,
            'qty_increments' => $qtyIncrements,
            'low_stock' => $lowStock,
            'low_stock_threshold' => $threshold,
            'status' => $salable ? 'IN_STOCK' : 'OUT_OF_STOCK',
            'message' => $message,
        ];
    }

    /**
     * @return array{stock_id:int,salable:bool,requested_salable:bool,salable_qty:?float}|null
     */
    private function msiAvailability(string $sku, string $websiteCode, float $requestedQty): ?array
    {
        if (!$this->moduleManager->isEnabled('Magento_InventorySalesApi')) {
            return null;
        }
        $resolverClass = 'Magento\\InventorySalesApi\\Api\\StockResolverInterface';
        $qtyClass = 'Magento\\InventorySalesApi\\Api\\GetProductSalableQtyInterface';
        $salableClass = 'Magento\\InventorySalesApi\\Api\\IsProductSalableInterface';
        $requestedClass = 'Magento\\InventorySalesApi\\Api\\IsProductSalableForRequestedQtyInterface';
        $salesChannelClass = 'Magento\\InventorySalesApi\\Api\\Data\\SalesChannelInterface';
        foreach ([$resolverClass, $qtyClass, $salableClass, $requestedClass, $salesChannelClass] as $class) {
            if (!interface_exists($class)) {
                return null;
            }
        }

        try {
            // Runtime resolution is isolated to this optional MSI bridge. It avoids a hard DI dependency
            // that would make the entire core fail when MSI modules are disabled.
            $om = ObjectManager::getInstance();
            $resolver = $om->get($resolverClass);
            $stock = $resolver->execute(constant($salesChannelClass . '::TYPE_WEBSITE'), $websiteCode);
            $stockId = (int)$stock->getStockId();
            $salable = (bool)$om->get($salableClass)->execute($sku, $stockId);
            $requested = $om->get($requestedClass)->execute($sku, $stockId, max(0.0001, $requestedQty));
            $requestedSalable = is_object($requested) && method_exists($requested, 'isSalable')
                ? (bool)$requested->isSalable()
                : $salable;
            $salableQty = null;
            try {
                $salableQty = (float)$om->get($qtyClass)->execute($sku, $stockId);
            } catch (\Throwable $e) {
                // Composite products may not expose a meaningful aggregate quantity.
                $this->logger->debug('Agentic inventory salable quantity is not available for SKU ' . $sku, ['exception' => $e]);
            }
            return [
                'stock_id' => $stockId,
                'salable' => $salable,
                'requested_salable' => $requestedSalable,
                'salable_qty' => $salableQty,
            ];
        } catch (\Throwable $e) {
            $this->logger->debug('Agentic MSI inventory bridge fell back to CatalogInventory.', ['exception' => $e]);
            return null;
        }
    }

    private function availabilityMessage(
        bool $salable,
        ?float $qty,
        bool $lowStock,
        bool $backorderable,
        bool $manageStock,
        float $requestedQty,
        bool $requestedSalable,
        bool $meetsMinimum,
        bool $meetsMaximum,
        bool $meetsIncrement,
        float $minSaleQty,
        float $maxSaleQty,
        float $qtyIncrements
    ): string {
        if (!$salable) {
            return (string)__('Out of stock.');
        }
        if (!$meetsMinimum) {
            return (string)__('The minimum purchase quantity is %1.', $this->formatQty($minSaleQty));
        }
        if (!$meetsMaximum) {
            return (string)__('The maximum purchase quantity is %1.', $this->formatQty($maxSaleQty));
        }
        if (!$meetsIncrement) {
            return (string)__('This product must be ordered in increments of %1.', $this->formatQty($qtyIncrements));
        }
        if (!$requestedSalable) {
            if ($qty !== null) {
                return (string)__('Only %1 is currently available, so the requested quantity of %2 cannot be fulfilled.', $this->formatQty($qty), $this->formatQty($requestedQty));
            }
            return (string)__('The product is in stock, but the requested quantity of %1 cannot currently be fulfilled.', $this->formatQty($requestedQty));
        }
        if ($backorderable) {
            return (string)__('The requested quantity can be ordered on backorder.');
        }
        if (!$manageStock) {
            return (string)__('In stock. Inventory quantity is not managed for this product.');
        }
        if ($qty !== null) {
            $formatted = $this->formatQty($qty);
            return $lowStock
                ? (string)__('Only %1 left in stock.', $formatted)
                : (string)__('%1 available in stock.', $formatted);
        }
        return (string)__('In stock.');
    }

    private function formatQty(float $qty): string
    {
        return abs($qty - round($qty)) < 0.00001
            ? (string)(int)round($qty)
            : rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }
}
