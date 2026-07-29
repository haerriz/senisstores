<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

interface ModifierInterface
{
    /**
     * @param string $value
     * @param Product $product
     * @param string|null $argument
     * @return string
     */
    public function modify($value, Product $product, $argument = null);
}
