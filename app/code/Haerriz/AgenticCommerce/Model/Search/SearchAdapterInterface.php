<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Search;

interface SearchAdapterInterface
{
    /**
     * Search products using normalized Agentic Commerce arguments.
     * Implementations may target native Magento search, Adobe Live Search,
     * or another catalog service while preserving this response contract.
     */
    public function search(array $arguments): array;
}
