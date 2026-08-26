<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Confirmation;

use Haerriz\AgenticCommerce\Api\ConfirmationActionHandlerInterface;

/**
 * DI-mergeable registry for consequential confirmed actions.
 *
 * Both conversational confirmations and exact/direct storefront confirmations resolve through the
 * same registry so an enterprise extension only registers a confirmation handler once.
 */
class ConfirmationActionRegistry
{
    /** @var array<string,ConfirmationActionHandlerInterface> */
    private array $handlers = [];

    /** @param ConfirmationActionHandlerInterface[] $handlers */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $handler) {
            if (!$handler instanceof ConfirmationActionHandlerInterface) {
                continue;
            }
            $action = trim($handler->action());
            if ($action !== '') {
                $this->handlers[$action] = $handler;
            }
        }
    }

    public function get(string $action): ?ConfirmationActionHandlerInterface
    {
        $handler = $this->handlers[$action] ?? null;
        return $handler instanceof ConfirmationActionHandlerInterface ? $handler : null;
    }

    public function has(string $action): bool
    {
        return $this->get($action) instanceof ConfirmationActionHandlerInterface;
    }

    /** @return string[] */
    public function actions(): array
    {
        return array_keys($this->handlers);
    }
}
