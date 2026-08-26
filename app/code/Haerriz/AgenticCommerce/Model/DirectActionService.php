<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Api\DirectActionSanitizerInterface;
use Haerriz\AgenticCommerce\Model\Action\IdempotencyService;
use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Audit\ToolAuditLogger;
use Haerriz\AgenticCommerce\Model\Conversation\ConversationRepository;
use Haerriz\AgenticCommerce\Model\Identity\IdentityResolver;
use Haerriz\AgenticCommerce\Model\Observability\TelemetryEmitter;
use Haerriz\AgenticCommerce\Model\Tool\ToolRegistry;
use Magento\Framework\Exception\LocalizedException;

/**
 * Deterministic storefront-action gateway.
 *
 * Exact card/form actions never round-trip through natural-language planning. Core and third-party
 * modules can register structured handlers, action->tool mappings and argument sanitizers through DI.
 */
class DirectActionService
{
    /** @var array<string,DirectActionHandlerInterface> */
    private array $handlerMap = [];

    /**
     * @param array<string,string> $actionMap
     * @param array<string,string> $actionLabels
     * @param DirectActionSanitizerInterface[] $argumentSanitizers
     * @param DirectActionHandlerInterface[] $handlers
     */
    public function __construct(
        private IdentityResolver $identities,
        private ToolRegistry $tools,
        private ToolPolicy $policy,
        private ConversationRepository $conversations,
        private ToolAuditLogger $audit,
        private IdempotencyService $idempotency,
        private TelemetryEmitter $telemetry,
        private array $actionMap = [],
        private array $actionLabels = [],
        private array $argumentSanitizers = [],
        array $handlers = []
    ) {
        foreach ($handlers as $handler) {
            if ($handler instanceof DirectActionHandlerInterface && $handler->action() !== '') {
                $this->handlerMap[$handler->action()] = $handler;
            }
        }
    }

    public function execute(string $action, array $arguments, array $clientContext = []): array
    {
        $action = mb_substr(trim($action), 0, 96);
        if ($action === '') throw new LocalizedException(__('A storefront action is required.'));

        $traceId = $this->telemetry->traceId();
        $clientId = isset($clientContext['client_id']) ? (string)$clientContext['client_id'] : null;
        $identity = $this->identities->resolve(null, $clientId, 'storefront');
        $cartId = isset($clientContext['cart_id']) ? (string)$clientContext['cart_id'] : null;
        $this->conversations->claimGuestConversations($identity);
        $conversation = $this->conversations->getOrStart(isset($clientContext['conversation_id']) ? (string)$clientContext['conversation_id'] : null, $identity);
        $conversationId = (string)$conversation['public_id'];
        $conversationPk = (int)$conversation['conversation_id'];

        $handler = $this->handlerMap[$action] ?? null;
        $toolName = $handler instanceof DirectActionHandlerInterface
            ? $handler->toolName()
            : (string)($this->actionMap[$action] ?? '');

        if ($toolName === '' || (!$handler && !$this->tools->has($toolName))) {
            throw new LocalizedException(__('Unsupported storefront action.'));
        }
        $this->policy->assertAllowed($toolName, $identity);

        $safeArguments = $handler instanceof DirectActionHandlerInterface
            ? $handler->sanitize($arguments)
            : $this->sanitizeArguments($toolName, $arguments);

        $label = $handler instanceof DirectActionHandlerInterface
            ? $handler->label($safeArguments)
            : $this->actionLabel($action, $safeArguments);
        $idempotencyKey = mb_substr(trim((string)($clientContext['idempotency_key'] ?? $arguments['_idempotency_key'] ?? '')), 0, 128);
        $scope = ((int)($identity['customer_id'] ?? 0) > 0
            ? 'customer:' . (int)$identity['customer_id']
            : 'guest:' . sha1((string)($identity['client_id'] ?? ''))) . '|' . $toolName;

        $idempotencyReserved = false;
        if ($idempotencyKey !== '' && $this->policy->isIdempotent($toolName)) {
            $requestHash = $this->idempotency->fingerprint([
                'tool' => $toolName,
                'arguments' => $safeArguments,
                'cart_id' => $cartId,
                'store_id' => (int)($identity['store_id'] ?? 0),
            ]);
            $cached = $this->idempotency->acquire($idempotencyKey, $scope, $toolName, $requestHash, (int)($identity['store_id'] ?? 0));
            if (is_array($cached)) {
                $cached['idempotent_replay'] = true;
                $this->audit->log($conversationPk, $identity, $toolName, $safeArguments, 'replay', 0, null);
                $this->telemetry->emit('direct_action.replay', ['trace_id' => $traceId, 'tool' => $toolName, 'action' => $action]);
                return $this->wrap($cached, $identity, $conversationId) + ['trace_id' => $traceId];
            }
            $idempotencyReserved = true;
        }

        // Persist only the first execution attempt. Network retries with the same idempotency key
        // replay the authoritative result without duplicating conversation turns.
        $this->conversations->appendMessage($conversationPk, 'user', $label, ['direct_action' => $action]);

        $context = [
            'identity' => $identity,
            'cart_id' => $cartId,
            'conversation_public_id' => $conversationId,
            'conversation_id' => $conversationId,
        ];
        $started = microtime(true);
        try {
            $result = $handler instanceof DirectActionHandlerInterface
                ? $handler->execute($safeArguments, $identity, $context)
                : $this->tools->execute($toolName, $safeArguments, $context);
            $duration = (int)round((microtime(true) - $started) * 1000);
            $this->audit->log($conversationPk, $identity, $toolName, $safeArguments, empty($result['error']) ? 'success' : 'error', $duration, empty($result['error']) ? null : 'direct_action_result_error');
            if ($idempotencyReserved) {
                if (empty($result['error'])) {
                    $this->idempotency->complete($idempotencyKey, $scope, $result, (int)($identity['store_id'] ?? 0));
                } else {
                    // A normal Magento validation/result error is safe to retry after the shopper changes input.
                    $this->idempotency->abandon($idempotencyKey, $scope, (int)($identity['store_id'] ?? 0));
                }
            }
            $this->telemetry->emit(empty($result['error']) ? 'direct_action.success' : 'direct_action.result_error', [
                'trace_id' => $traceId,
                'tool' => $toolName,
                'action' => $action,
                'duration_ms' => $duration,
            ]);
        } catch (\Throwable $e) {
            if ($idempotencyReserved) {
                $this->idempotency->markUncertain($idempotencyKey, $scope, (int)($identity['store_id'] ?? 0));
            }
            $duration = (int)round((microtime(true) - $started) * 1000);
            $this->audit->log($conversationPk, $identity, $toolName, $safeArguments, 'exception', $duration, $e::class);
            $this->telemetry->emit('direct_action.failure', ['trace_id' => $traceId, 'tool' => $toolName, 'action' => $action, 'duration_ms' => $duration]);
            throw $e;
        }

        $message = (string)($result['assistant_message'] ?? __('Done.'));
        $this->conversations->appendMessage($conversationPk, 'assistant', $message, $result);
        return $this->wrap($result, $identity, $conversationId) + ['trace_id' => $traceId];
    }

    private function sanitizeArguments(string $toolName, array $arguments): array
    {
        foreach ($this->argumentSanitizers as $sanitizer) {
            if ($sanitizer instanceof DirectActionSanitizerInterface && $sanitizer->supports($toolName)) {
                return $sanitizer->sanitize($toolName, $arguments);
            }
        }
        throw new LocalizedException(__('No secure direct-action argument sanitizer is registered for this capability.'));
    }

    private function actionLabel(string $action, array $arguments): string
    {
        $template = trim((string)($this->actionLabels[$action] ?? ''));
        if ($template !== '') {
            $sku = (string)($arguments['sku'] ?? $arguments['product_sku'] ?? __('product'));
            return str_replace(['{sku}', '{action}'], [$sku, $action], $template);
        }
        return ucwords(str_replace('_', ' ', $action));
    }

    private function wrap(array $result, array $identity, string $conversationId): array
    {
        return $result + [
            'conversation_id' => $conversationId,
            'client_id' => (string)$identity['client_id'],
            'viewer' => [
                'is_customer' => (bool)$identity['is_customer'],
                'customer_id' => (int)$identity['customer_id'] ?: null,
            ],
        ];
    }
}
