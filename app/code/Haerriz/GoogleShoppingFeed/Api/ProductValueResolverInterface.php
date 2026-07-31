<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\Product;

interface ProductValueResolverInterface
{
    public function resolve(array $mapping, Product $product, FeedProfileInterface $profile);
}
