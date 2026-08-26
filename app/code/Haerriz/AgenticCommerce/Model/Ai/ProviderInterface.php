<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

interface ProviderInterface
{
    /** Return null when the provider is disabled or could not produce a valid tool plan. */
    public function plan(string $message, array $context, array $toolDefinitions): ?array;
}
