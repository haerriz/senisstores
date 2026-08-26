<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Extension point for structured storefront actions that require domain-specific handling.
 *
 * Implementations receive only trusted identity plus bounded structured arguments. They must not
 * bypass Magento service contracts or repositories. Registration is DI-driven in DirectActionService.
 */
interface DirectActionHandlerInterface
{
    public function action(): string;

    /** ToolPolicy capability name used for authorization/audit/idempotency metadata. */
    public function toolName(): string;

    /** Shopper-visible conversation label for the deterministic action. */
    public function label(array $arguments): string;

    /** Validate and normalize the structured client arguments before execution. */
    public function sanitize(array $arguments): array;

    /**
     * @param array<string,mixed> $arguments
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function execute(array $arguments, array $identity, array $context = []): array;
}
