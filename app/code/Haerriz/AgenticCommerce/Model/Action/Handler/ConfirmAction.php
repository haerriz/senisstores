<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action\Handler;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationActionRegistry;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Magento\Framework\Exception\LocalizedException;

class ConfirmAction implements DirectActionHandlerInterface
{
    public function __construct(
        private ConfirmationService $confirmations,
        private ConfirmationActionRegistry $registry
    ) {}

    public function action(): string { return 'confirm_action'; }
    public function toolName(): string { return 'confirm_pending_action'; }
    public function label(array $arguments): string { return (string)__('Confirm action'); }

    public function sanitize(array $arguments): array
    {
        $token = mb_substr(trim((string)($arguments['token'] ?? '')), 0, 160);
        if ($token === '') {
            throw new LocalizedException(__('A confirmation token is required.'));
        }
        return ['token' => $token];
    }

    public function execute(array $arguments, array $identity, array $context = []): array
    {
        $pending = $this->confirmations->consume((string)$arguments['token'], $identity);
        $action = (string)($pending['action'] ?? '');
        $handler = $this->registry->get($action);
        if ($handler === null) {
            throw new LocalizedException(__('That confirmation action is not supported.'));
        }
        return $handler->execute((array)($pending['payload'] ?? []), $identity, $context);
    }
}
