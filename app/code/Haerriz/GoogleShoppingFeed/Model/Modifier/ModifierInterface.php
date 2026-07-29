<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

interface ModifierInterface
{
    /**
     * @param string $value
     * @param Product $product
     * @return string
     */
    public function modify($value, Product $product);
}
