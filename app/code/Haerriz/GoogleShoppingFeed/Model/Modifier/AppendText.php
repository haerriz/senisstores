<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

class AppendText implements ModifierInterface
{
    /**
     * @inheritdoc
     */
    public function modify($value, Product $product, $argument = null)
    {
        if ($argument !== null) {
            return $value . $argument;
        }
        return $value;
    }
}
