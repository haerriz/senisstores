<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

use Magento\Catalog\Model\Product;

interface TypeStrategyInterface
{
    /**
     * Expand a catalog product into the concrete offer products that should appear in the feed.
     *
     * @param Product $product
     * @return Product[]
     */
    public function resolveProducts(Product $product): array;

    /**
     * Optional type-specific metadata used by resolvers/modifiers.
     *
     * @param Product $product
     * @return array
     */
    public function prepareData(Product $product): array;
}
