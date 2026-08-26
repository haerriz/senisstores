<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SetShippingMethodResolver implements ResolverInterface
{
    public function __construct(private CheckoutService $checkout, private CustomerContext $customerContext, private IdempotentExecutor $idempotent) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, $input['client_id'] ?? null, 'set_shipping_method');
        return $this->idempotent->execute('set_shipping_method', $input, $identity, fn(): array =>
            $this->checkout->setShippingMethod($identity, (string)($input['carrier_code'] ?? ''), (string)($input['method_code'] ?? ''), $input['cart_id'] ?? null)
        );
    }
}
