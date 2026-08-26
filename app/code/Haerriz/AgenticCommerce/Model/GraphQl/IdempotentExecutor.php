<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\GraphQl;

use Haerriz\AgenticCommerce\Model\Action\IdempotencyService;
use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Observability\TelemetryEmitter;

/**
 * Shared GraphQL governance/replay wrapper for storefront mutations.
 *
 * ToolPolicy is intentionally asserted here as defense in depth even though normal resolvers obtain
 * identity through CustomerContext::identityForTool(). Enterprise/extension resolvers can reuse this
 * executor and still receive the same authorization boundary, idempotency and telemetry behavior.
 */
class IdempotentExecutor
{
    public function __construct(
        private IdempotencyService $idempotency,
        private ToolPolicy $policy,
        private TelemetryEmitter $telemetry
    ) {}

    public function execute(string $toolName, array $input, array $identity, callable $operation): array
    {
        $this->policy->assertAllowed($toolName, $identity);
        $traceId = $this->telemetry->traceId();
        $storeId = (int)($identity['store_id'] ?? 0);
        $started = microtime(true);
        $key = mb_substr(trim((string)($input['idempotency_key'] ?? '')), 0, 128);
        $idempotent = $key !== '' && $this->policy->isIdempotent($toolName);
        $this->telemetry->emit('graphql_mutation.started', [
            'trace_id' => $traceId,
            'tool' => $toolName,
            'store_id' => $storeId,
            'idempotency_enabled' => $idempotent,
        ]);

        if (!$idempotent) {
            try {
                $result = (array)$operation();
                $this->telemetry->emit(empty($result['error']) ? 'graphql_mutation.success' : 'graphql_mutation.result_error', [
                    'trace_id' => $traceId,
                    'tool' => $toolName,
                    'store_id' => $storeId,
                    'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                ]);
                return $result;
            } catch (\Throwable $e) {
                $this->telemetry->emit('graphql_mutation.failure', [
                    'trace_id' => $traceId,
                    'tool' => $toolName,
                    'store_id' => $storeId,
                    'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                    'exception_class' => $e::class,
                ]);
                throw $e;
            }
        }

        $guestAnchor = trim((string)($input['cart_id'] ?? '')) !== ''
            ? 'cart:' . hash('sha256', (string)$input['cart_id'])
            : 'client:' . hash('sha256', (string)($identity['client_id'] ?? ''));
        $scope = ((int)($identity['customer_id'] ?? 0) > 0
            ? 'customer:' . (int)$identity['customer_id']
            : 'guest:' . $guestAnchor) . '|graphql|' . $toolName;
        $fingerprintInput = $input;
        unset($fingerprintInput['idempotency_key']);
        $requestHash = $this->idempotency->fingerprint([
            'tool' => $toolName,
            'input' => $fingerprintInput,
            'store_id' => $storeId,
        ]);
        $cached = $this->idempotency->acquire($key, $scope, $toolName, $requestHash, $storeId);
        if (is_array($cached)) {
            $cached['idempotent_replay'] = true;
            $this->telemetry->emit('graphql_mutation.replay', [
                'trace_id' => $traceId,
                'tool' => $toolName,
                'store_id' => $storeId,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            return $cached;
        }

        try {
            $result = (array)$operation();
            if (empty($result['error'])) {
                $this->idempotency->complete($key, $scope, $result, $storeId);
            } else {
                $this->idempotency->abandon($key, $scope, $storeId);
            }
            $this->telemetry->emit(empty($result['error']) ? 'graphql_mutation.success' : 'graphql_mutation.result_error', [
                'trace_id' => $traceId,
                'tool' => $toolName,
                'store_id' => $storeId,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            return $result;
        } catch (\Throwable $e) {
            // If Magento may have committed before the exception/network interruption, fail closed.
            $this->idempotency->markUncertain($key, $scope, $storeId);
            $this->telemetry->emit('graphql_mutation.failure', [
                'trace_id' => $traceId,
                'tool' => $toolName,
                'store_id' => $storeId,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'exception_class' => $e::class,
            ]);
            throw $e;
        }
    }
}
