<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Store\StoreInformationService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class StoreProfileResolver implements ResolverInterface
{
    public function __construct(private StoreInformationService $service) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        return $this->service->get();
    }
}
