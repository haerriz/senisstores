<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action\Handler;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Magento\Framework\Exception\LocalizedException;

class UpdateCustomerProfile implements DirectActionHandlerInterface
{
    public function __construct(private CustomerAccountService $customers) {}
    public function action(): string { return 'update_customer_profile'; }
    public function toolName(): string { return 'update_customer_profile'; }
    public function label(array $arguments): string { return (string)__('Update customer profile'); }
    public function sanitize(array $arguments): array
    {
        $first = mb_substr(trim((string)($arguments['firstname'] ?? '')), 0, 255);
        $last = mb_substr(trim((string)($arguments['lastname'] ?? '')), 0, 255);
        if ($first === '' && $last === '') throw new LocalizedException(__('Enter a first name or last name to update.'));
        return ['firstname' => $first ?: null, 'lastname' => $last ?: null];
    }
    public function execute(array $arguments, array $identity, array $context = []): array
    {
        $customer = $this->customers->updateProfile($identity, $arguments);
        return ['customer' => $customer, 'assistant_message' => (string)($customer['assistant_message'] ?? __('Your profile was updated.'))];
    }
}
