<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

interface ProductTypeResolverInterface
{
    /**
     * Preload child products and relationships for one parent batch.
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @param FeedProfileInterface $profile
     * @return void
     */
    public function prepare($collection, FeedProfileInterface $profile);

    /**
     * Return the deterministic feed rows represented by a catalog product.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param FeedProfileInterface $profile
     * @return \Magento\Catalog\Model\Product[]
     */
    public function resolve($product, FeedProfileInterface $profile);
}
