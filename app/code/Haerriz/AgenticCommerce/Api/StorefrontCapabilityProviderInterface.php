<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Optional Magento/Adobe Commerce/third-party modules can register providers through DI without
 * adding hard dependencies to the core module. Their operational tools still register in ToolRegistry.
 */
interface StorefrontCapabilityProviderInterface
{
    public function getCode(): string;
    public function isAvailable(?int $storeId = null): bool;
    /** @return string[] */
    public function getCapabilities(?int $storeId = null): array;
}
