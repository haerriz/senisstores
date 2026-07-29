<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

class RoundPrice implements ModifierInterface
{
    /**
     * @param string $value
     * @param Product $product
     * @param string|null $argument
     * @return string
     */
    public function modify($value, Product $product, $argument = null)
    {
        if (is_numeric($value)) {
            return number_format(round((float)$value, 2), 2, '.', '');
        }
        return $value;
    }
}
