<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

class Bundle implements TypeStrategyInterface
{
    public function prepareData(\Magento\Catalog\Model\Product $product): array { return ['sku' => $product->getSku()]; }
}
