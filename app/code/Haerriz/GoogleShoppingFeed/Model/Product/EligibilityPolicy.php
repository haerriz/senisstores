<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product;

use Haerriz\GoogleShoppingFeed\Api\ProductEligibilityPolicyInterface;
use Magento\Catalog\Model\Product;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

class EligibilityPolicy implements ProductEligibilityPolicyInterface
{
    public function isEligible(Product $product, FeedProfileInterface $profile): bool
    {
        return $product->getStatus() == 1 && $product->isSalable();
    }
}
