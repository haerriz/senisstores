<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Pre-execution intent guard. Localized/vertical modules may provide their own grammar without
 * modifying AgentService. Unknown mutations remain denied by the pool.
 */
interface ToolIntentGuardInterface
{
    public function supports(string $toolName): bool;
    public function isAllowed(string $toolName,string $message,array $arguments,array $context=[]):bool;
}
