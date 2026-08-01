<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

use Magento\Catalog\Model\Product;

class Bundle implements TypeStrategyInterface
{
    public function resolveProducts(Product $product): array
    {
        // Google Shopping typically expects the sellable bundle parent as one offer.
        return [$product];
    }

    public function prepareData(Product $product): array
    {
        return [
            'sku' => (string)$product->getSku(),
            'type' => 'bundle',
            'is_bundle' => true,
        ];
    }
}
