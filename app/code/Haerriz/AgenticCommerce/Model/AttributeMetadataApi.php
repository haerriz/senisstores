<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\AttributeMetadataInterface;

class AttributeMetadataApi implements AttributeMetadataInterface
{
    public function __construct(private AttributeMetadataService $metadataService)
    {
    }

    public function getList(?string $search = null): array
    {
        return $this->metadataService->getMetadata($search);
    }
}
