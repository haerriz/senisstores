<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Enterprise policy hook. Providers may throw AuthorizationException/LocalizedException to deny
 * a tool for customer-group, B2B company, catalog-permission, geo, fraud or merchant policy reasons.
 */
interface ToolAuthorizationProviderInterface
{
    public function assertAllowed(string $toolName, array $identity, array $metadata): void;
}
