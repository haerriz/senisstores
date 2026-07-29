<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

class Capitalize implements ModifierInterface
{
    /**
     * @inheritdoc
     */
    public function modify($value, Product $product, $argument = null)
    {
        return mb_strtoupper($value);
    }
}
