<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Agent;

use Haerriz\AgenticCommerce\Api\StorefrontCapabilityProviderInterface;

class ExtensionCapabilityRegistry
{
    /** @param StorefrontCapabilityProviderInterface[] $providers */
    public function __construct(private array $providers = []) {}

    public function get(?int $storeId = null): array
    {
        $out = [];
        foreach ($this->providers as $provider) {
            if (!$provider instanceof StorefrontCapabilityProviderInterface || !$provider->isAvailable($storeId)) continue;
            $out[] = ['code' => $provider->getCode(), 'capabilities' => array_values(array_unique($provider->getCapabilities($storeId)))];
        }
        return $out;
    }
}
