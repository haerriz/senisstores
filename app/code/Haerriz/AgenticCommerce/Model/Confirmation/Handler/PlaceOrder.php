<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Confirmation\Handler;

use Haerriz\AgenticCommerce\Api\ConfirmationActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class PlaceOrder implements ConfirmationActionHandlerInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function action(): string { return 'place_order'; }
    public function execute(array $payload, array $identity, array $context = []): array
    {
        return $this->checkout->placeConfirmedOrder($identity, $payload, $context['cart_id'] ?? null);
    }
}
