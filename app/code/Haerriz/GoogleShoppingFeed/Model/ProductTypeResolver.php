<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductTypeResolverInterface;
use Haerriz\GoogleShoppingFeed\Model\Product\Type\Pool;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class ProductTypeResolver implements ProductTypeResolverInterface
{
    private Pool $typePool;

    public function __construct(Pool $typePool)
    {
        $this->typePool = $typePool;
    }

    public function prepare($collection, FeedProfileInterface $profile)
    {
        // Warm type instances so subsequent resolve() calls avoid repeated lazy loads.
        foreach ($collection as $product) {
            if ($product instanceof Product) {
                $product->getTypeInstance();
            }
        }
    }

    public function resolve($product, FeedProfileInterface $profile)
    {
        if (!$product instanceof Product) {
            return [];
        }

        $strategy = $this->typePool->getStrategy((string)$product->getTypeId());
        $resolved = $strategy->resolveProducts($product);

        return array_values(array_filter(
            $resolved,
            fn($item) => $item instanceof Product && $this->isExportable($item)
        ));
    }

    public function resolveType(Product $product): string
    {
        return (string)$product->getTypeId();
    }

    public function isExportable(Product $product): bool
    {
        return (int)$product->getStatus() === Status::STATUS_ENABLED;
    }
}
