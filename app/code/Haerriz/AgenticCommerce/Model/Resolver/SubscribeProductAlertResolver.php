<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Haerriz\AgenticCommerce\Model\Product\ProductAlertService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SubscribeProductAlertResolver implements ResolverInterface
{
    public function __construct(private ProductAlertService $alerts, private CustomerContext $customerContext, private IdempotentExecutor $idempotent) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, null, 'subscribe_product_alert');
        return $this->idempotent->execute('subscribe_product_alert', $input, $identity, fn(): array =>
            $this->alerts->subscribe($identity, (string)($input['sku'] ?? ''), (string)($input['type'] ?? ''))
        );
    }
}
