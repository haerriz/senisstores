<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface ProductEligibilityPolicyInterface
{
    public function isEligible(\Magento\Catalog\Model\Product $product, \Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface $profile): bool;
}
