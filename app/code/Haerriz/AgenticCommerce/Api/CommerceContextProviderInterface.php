<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Extension point for adding bounded, non-sensitive storefront context to the agent graph.
 *
 * Implementations MUST return shopper-safe derived context only. The core applies a second
 * sanitization pass and strips identifiers/secrets before the graph can reach an AI provider.
 */
interface CommerceContextProviderInterface
{
    /**
     * @param array<string,mixed> $context Server-owned commerce context.
     * @return array<string,mixed> Shopper-safe derived context.
     */
    public function getContext(array $context): array;
}
