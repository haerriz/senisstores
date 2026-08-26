<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class UpdateCustomerProfileResolver implements ResolverInterface
{
    public function __construct(private CustomerAccountService $customers, private CustomerContext $customerContext, private IdempotentExecutor $idempotent) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, null, 'update_customer_profile');
        return $this->idempotent->execute('update_customer_profile', $input, $identity, function () use ($identity, $input): array {
            $payload = $input;
            unset($payload['idempotency_key']);
            return $this->customers->updateProfile($identity, $payload);
        });
    }
}
