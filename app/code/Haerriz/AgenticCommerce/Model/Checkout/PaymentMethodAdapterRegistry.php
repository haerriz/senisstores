<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Checkout;

use Haerriz\AgenticCommerce\Api\PaymentMethodAdapterInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class PaymentMethodAdapterRegistry
{
    /** @param PaymentMethodAdapterInterface[] $adapters */
    public function __construct(private array $adapters = []) {}
    public function apply(string $methodCode, PaymentInterface $payment, array $payload, array $context = []): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof PaymentMethodAdapterInterface && $adapter->supports($methodCode)) {
                $adapter->apply($payment, $payload, $context); return;
            }
        }
    }
}
