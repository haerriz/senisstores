<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action\Handler;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Magento\Framework\Exception\LocalizedException;

class SaveCustomerAddress implements DirectActionHandlerInterface
{
    public function __construct(private CustomerAccountService $customers) {}
    public function action(): string { return 'save_customer_address'; }
    public function toolName(): string { return 'save_customer_address'; }
    public function label(array $arguments): string { return (string)__('Save customer address'); }
    public function sanitize(array $arguments): array
    {
        $safe = $arguments;
        unset($safe['customer_id'], $safe['password'], $safe['token'], $safe['authorization']);
        if (isset($safe['address_id'])) $safe['address_id'] = max(0, (int)$safe['address_id']);
        if (!isset($safe['street']) && !isset($safe['city']) && empty($safe['address_id'])) {
            throw new LocalizedException(__('Address details are required.'));
        }
        return $safe;
    }
    public function execute(array $arguments, array $identity, array $context = []): array
    {
        $addressId = isset($arguments['address_id']) && (int)$arguments['address_id'] > 0 ? (int)$arguments['address_id'] : null;
        return $this->customers->saveAddress($identity, $arguments, $addressId);
    }
}
