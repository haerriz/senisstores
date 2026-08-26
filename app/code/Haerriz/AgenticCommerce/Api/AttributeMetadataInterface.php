<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

interface AttributeMetadataInterface
{
    /**
     * Return storefront-safe product attribute metadata used by the agent.
     *
     * @param string|null $search
     * @return mixed[]
     */
    public function getList(?string $search = null): array;
}
