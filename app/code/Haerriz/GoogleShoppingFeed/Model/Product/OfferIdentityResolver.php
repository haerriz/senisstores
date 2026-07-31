<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product;

use Haerriz\GoogleShoppingFeed\Api\OfferIdentityResolverInterface;
use Magento\Catalog\Model\Product;

class OfferIdentityResolver implements OfferIdentityResolverInterface
{
    public function resolve(Product $product): string
    {
        return (string)$product->getSku();
    }
}
