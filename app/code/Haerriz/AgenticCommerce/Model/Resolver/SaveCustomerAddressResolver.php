<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SaveCustomerAddressResolver implements ResolverInterface
{
    public function __construct(private CustomerAccountService $customers, private CustomerContext $customerContext, private IdempotentExecutor $idempotent) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, null, 'save_customer_address');
        return $this->idempotent->execute('save_customer_address', $input, $identity, function () use ($identity, $input): array {
            $payload = $input;
            $addressId = isset($payload['address_id']) ? (int)$payload['address_id'] : null;
            unset($payload['address_id'], $payload['idempotency_key']);
            return $this->customers->saveAddress($identity, $payload, $addressId);
        });
    }
}
