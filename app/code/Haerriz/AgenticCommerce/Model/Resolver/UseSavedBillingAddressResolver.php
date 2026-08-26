<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class UseSavedBillingAddressResolver implements ResolverInterface
{
    public function __construct(
        private CustomerAccountService $customers,
        private CheckoutService $checkout,
        private CustomerContext $customerContext,
        private IdempotentExecutor $idempotent
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, $input['client_id'] ?? null, 'use_saved_billing_address');
        return $this->idempotent->execute('use_saved_billing_address', $input, $identity, function () use ($identity, $input): array {
            $address = $this->customers->ownedAddress($identity, (int)($input['address_id'] ?? 0));
            return $this->checkout->useCustomerAddress($identity, 'billing', $address, $input['cart_id'] ?? null);
        });
    }
}
