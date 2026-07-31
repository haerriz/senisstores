<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\Product;

interface ModifierPipelineInterface
{
    public function apply($value, array $modifiers, Product $product, FeedProfileInterface $profile);

    public function validate(array $modifiers);
}
