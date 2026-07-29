<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;

class PrependText implements ModifierInterface
{
    /**
     * @inheritdoc
     */
    public function modify($value, Product $product, $argument = null)
    {
        if ($argument !== null) {
            return $argument . $value;
        }
        return $value;
    }
}
