<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Confirmation\Handler;

use Haerriz\AgenticCommerce\Api\ConfirmationActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Magento\Framework\Exception\LocalizedException;

class DeleteCustomerAddress implements ConfirmationActionHandlerInterface
{
    public function __construct(private CustomerAccountService $customers) {}
    public function action(): string { return 'delete_customer_address'; }
    public function execute(array $payload, array $identity, array $context = []): array
    {
        $addressId = (int)($payload['address_id'] ?? 0);
        if ($addressId <= 0) throw new LocalizedException(__('The confirmed address is no longer valid.'));
        return $this->customers->deleteAddress($identity, $addressId);
    }
}
