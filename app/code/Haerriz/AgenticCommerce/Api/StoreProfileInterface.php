<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

interface StoreProfileInterface
{
    /** @return mixed[] */
    public function get(): array;
}
