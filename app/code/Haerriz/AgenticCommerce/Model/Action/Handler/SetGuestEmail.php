<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action\Handler;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;
use Magento\Framework\Exception\LocalizedException;

class SetGuestEmail implements DirectActionHandlerInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function action(): string { return 'set_guest_email'; }
    public function toolName(): string { return 'set_guest_email'; }
    public function label(array $arguments): string { return (string)__('Set checkout email'); }
    public function sanitize(array $arguments): array
    {
        $email = mb_substr(trim((string)($arguments['email'] ?? '')), 0, 254);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Enter a valid email address.'));
        }
        return ['email' => $email];
    }
    public function execute(array $arguments, array $identity, array $context = []): array
    {
        $result = $this->checkout->setGuestEmail($identity, (string)$arguments['email'], $context['cart_id'] ?? null);
        $result['assistant_message'] = (string)__('Checkout email saved.');
        return $result;
    }
}
