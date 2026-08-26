<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\StoreProfileInterface;
use Haerriz\AgenticCommerce\Model\Store\StoreInformationService;

class StoreProfileApi implements StoreProfileInterface
{
    public function __construct(private StoreInformationService $service) {}

    public function get(): array
    {
        return $this->service->get();
    }
}
