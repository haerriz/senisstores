<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationActionRegistry;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Magento\Framework\Exception\LocalizedException;

/** Confirms the latest server-owned pending action through the shared enterprise registry. */
class ConfirmPendingAction implements ToolInterface
{
    public function __construct(
        private ConfirmationService $confirmations,
        private ConfirmationActionRegistry $registry
    ) {}

    public function getName(): string { return 'confirm_pending_action'; }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Execute the latest server-side pending confirmation after the shopper explicitly says confirm.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $identity = (array)($context['identity'] ?? []);
        $pending = $this->confirmations->consumeLatest((string)($context['conversation_public_id'] ?? ''), $identity);
        $action = (string)($pending['action'] ?? '');
        $handler = $this->registry->get($action);
        if ($handler === null) {
            throw new LocalizedException(__('That pending action is not supported.'));
        }
        return $handler->execute((array)($pending['payload'] ?? []), $identity, $context);
    }
}
