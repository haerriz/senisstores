<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Executes a previously prepared, server-side confirmation action.
 * Third-party modules may register additional consequential workflows without changing core.
 */
interface ConfirmationActionHandlerInterface
{
    public function action(): string;

    /** @return array<string,mixed> */
    public function execute(array $payload, array $identity, array $context = []): array;
}
