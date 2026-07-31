<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\ProductTypeResolverInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\Product;

class ProductTypeResolver implements ProductTypeResolverInterface
{
    public function prepare($collection, FeedProfileInterface $profile)
    {
        // Preload catalog collection attributes if needed
    }

    public function resolve($product, FeedProfileInterface $profile)
    {
        if ($product instanceof Product) {
            return [$product];
        }
        return [];
    }

    public function resolveType(Product $product): string
    {
        return (string)$product->getTypeId();
    }

    public function isExportable(Product $product): bool
    {
        return (int)$product->getStatus() === \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;
    }
}
