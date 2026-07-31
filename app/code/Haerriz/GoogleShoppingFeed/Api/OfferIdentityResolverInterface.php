<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface OfferIdentityResolverInterface
{
    public function resolve(\Magento\Catalog\Model\Product $product): string;
}
