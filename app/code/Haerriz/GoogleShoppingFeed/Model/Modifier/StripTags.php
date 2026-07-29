<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

class StripTags implements ModifierInterface
{
    /**
     * @param string $value
     * @param Product $product
     * @return string
     */
    public function modify($value, Product $product)
    {
        return strip_tags($value);
    }
}
