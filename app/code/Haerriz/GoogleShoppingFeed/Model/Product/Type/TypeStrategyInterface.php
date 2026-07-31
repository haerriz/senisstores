<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

interface TypeStrategyInterface
{
    public function prepareData(\Magento\Catalog\Model\Product $product): array;
}
