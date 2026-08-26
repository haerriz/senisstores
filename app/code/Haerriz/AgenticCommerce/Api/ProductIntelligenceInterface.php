<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Api;
interface ProductIntelligenceInterface
{
    /**
     * @param string $sku
     * @return mixed[]
     */
    public function get(string $sku): array;

    /**
     * @param string[] $skus
     * @param string|null $focus
     * @param string|null $goal
     * @return mixed[]
     */
    public function compare(array $skus, ?string $focus = null, ?string $goal = null): array;

    /**
     * @param string $sku
     * @param string $question
     * @return mixed[]
     */
    public function question(string $sku, string $question): array;
}
