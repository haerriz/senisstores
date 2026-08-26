<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Optional deterministic planner extension point for locales, verticals and enterprise modules.
 * Return null when the provider does not own the shopper message.
 */
interface PlannerRuleProviderInterface
{
    public function plan(string $message, array $context = []): ?array;
}
