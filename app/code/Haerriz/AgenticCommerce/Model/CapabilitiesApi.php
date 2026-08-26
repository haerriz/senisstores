<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\CapabilitiesInterface;
use Haerriz\AgenticCommerce\Model\Agent\CapabilityService;

class CapabilitiesApi implements CapabilitiesInterface
{
    public function __construct(private CapabilityService $capabilities)
    {
    }

    public function get(): array
    {
        return $this->capabilities->get();
    }
}
