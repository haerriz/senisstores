<?php
namespace Haerriz\GoogleShoppingFeed\Model\Taxonomy;

use Haerriz\GoogleShoppingFeed\Api\CategoryMappingRepositoryInterface;

class CategoryMappingRepository implements CategoryMappingRepositoryInterface
{
    public function save(array $mappingData)
    {
        return true;
    }
}
