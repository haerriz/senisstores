<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface CategoryMappingRepositoryInterface
{
    public function save(array $mappingData);
}
