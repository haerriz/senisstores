<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action\Handler;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;
use Magento\Framework\Exception\LocalizedException;

class SetCheckoutAddress implements DirectActionHandlerInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function action(): string { return 'set_checkout_address'; }
    public function toolName(): string { return 'set_checkout_address'; }
    public function label(array $arguments): string { return (string)__('Set checkout address'); }
    public function sanitize(array $arguments): array
    {
        $kind = (string)($arguments['kind'] ?? '');
        if (!in_array($kind, ['shipping', 'billing'], true)) {
            throw new LocalizedException(__('Choose shipping or billing address.'));
        }
        $address = is_array($arguments['address'] ?? null) ? $arguments['address'] : [];
        if ($address === []) throw new LocalizedException(__('Address details are required.'));
        return ['kind' => $kind, 'address' => $address];
    }
    public function execute(array $arguments, array $identity, array $context = []): array
    {
        $result = $this->checkout->setAddress($identity, (string)$arguments['kind'], (array)$arguments['address'], $context['cart_id'] ?? null);
        $result['assistant_message'] = (string)__('%1 address saved for checkout.', ucfirst((string)$arguments['kind']));
        return $result;
    }
}
