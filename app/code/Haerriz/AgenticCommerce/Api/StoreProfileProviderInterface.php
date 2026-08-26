<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

interface StoreProfileProviderInterface
{
    public function getCode(): string;

    /** @return mixed[] Public, non-secret storefront facts only. */
    public function getProfile(int $storeId): array;
}
