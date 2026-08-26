<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

interface AgentInterface
{
    /**
     * Execute one agent turn.
     *
     * @param string $message
     * @param string|null $sessionId
     * @param string|null $context JSON object supplied by the client.
     * @return mixed[]
     */
    public function chat(string $message, ?string $sessionId = null, ?string $context = null): array;
}
