<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

interface ProductProviderInterface
{
    /**
     * Build a deterministic, store-scoped, memory-bounded eligible product collection.
     *
     * @param FeedProfileInterface $profile
     * @param \Haerriz\GoogleShoppingFeed\Model\Rule|null $rule
     * @param int $afterEntityId
     * @param int $pageSize
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getCollection(
        FeedProfileInterface $profile,
        $rule = null,
        $afterEntityId = 0,
        $pageSize = 500
    );
}
