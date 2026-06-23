<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Model;

use Magento\CatalogInventory\Api\StockRegistryInterface;

class AvailabilityResolver
{
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(
        StockRegistryInterface $stockRegistry
    ) {
        $this->stockRegistry = $stockRegistry;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function resolve(\Magento\Catalog\Model\Product $product)
    {
        if (!(int) $product->getStatus()) {
            return 'out of stock';
        }

        $stockItem = $this->stockRegistry->getStockItem((int) $product->getId());

        if (!$stockItem->getIsInStock()) {
            return 'out of stock';
        }

        if ($stockItem->getQty() <= 0 && !$stockItem->getBackorders()) {
            return 'out of stock';
        }

        return 'in stock';
    }
}
