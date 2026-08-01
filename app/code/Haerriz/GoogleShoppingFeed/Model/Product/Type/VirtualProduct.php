<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

use Magento\Catalog\Model\Product;

class VirtualProduct implements TypeStrategyInterface
{
    public function resolveProducts(Product $product): array
    {
        return [$product];
    }

    public function prepareData(Product $product): array
    {
        return [
            'sku' => (string)$product->getSku(),
            'type' => 'virtual',
            'shipping_weight' => 0,
            'is_virtual' => true,
        ];
    }
}
