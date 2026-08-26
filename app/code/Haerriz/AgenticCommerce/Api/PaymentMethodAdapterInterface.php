<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Extension point for tokenized/gateway-specific payment metadata. Implementations MUST never ask the
 * LLM for raw PAN/CVV/password credentials; collect secrets through the provider's secure UI/SDK.
 */
interface PaymentMethodAdapterInterface
{
    public function supports(string $methodCode): bool;
    public function apply(PaymentInterface $payment, array $safePayload, array $context = []): void;
}
