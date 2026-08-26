<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

interface ResponseProviderInterface
{
    /**
     * Convert already-authoritative, privacy-filtered Magento facts into a natural response.
     * Return null to keep the deterministic/tool-produced fallback message.
     */
    public function synthesize(string $message, array $facts, array $context = []): ?string;
}
