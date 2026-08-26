<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

/**
 * Enterprise observability extension point for OpenTelemetry/New Relic/Datadog/SIEM adapters.
 * Implementations receive already-sanitized scalar attributes only.
 */
interface TelemetryProcessorInterface
{
    /** @param array<string,scalar|null> $attributes */
    public function emit(string $event, array $attributes): void;
}
