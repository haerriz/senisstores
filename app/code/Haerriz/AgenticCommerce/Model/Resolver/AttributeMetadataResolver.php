<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\AttributeMetadataService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class AttributeMetadataResolver implements ResolverInterface
{
    public function __construct(private AttributeMetadataService $metadataService)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        return $this->metadataService->getMetadata(isset($args['search']) ? (string)$args['search'] : null);
    }
}
