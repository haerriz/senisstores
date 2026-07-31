<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface TaxonomyRepositoryInterface
{
    public function search(string $query): array;
}
