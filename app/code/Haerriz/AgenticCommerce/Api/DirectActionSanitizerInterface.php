<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/** Securely normalizes arguments for exact/direct storefront actions. */
interface DirectActionSanitizerInterface
{
    public function supports(string $toolName): bool;
    public function sanitize(string $toolName, array $arguments): array;
}
