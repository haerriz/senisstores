<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Recommendation;
interface RecommendationAdapterInterface
{
    public function recommend(string $sku, string $type = 'related', int $limit = 6, int $storeId = 0): array;
}
