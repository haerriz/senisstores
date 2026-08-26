<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Durable idempotency for retry-safe storefront mutations.
 *
 * A DB-backed reservation prevents concurrent duplicate requests from both executing. Reusing the
 * same key with a different request fingerprint is rejected instead of replaying the wrong result.
 */
class IdempotencyService
{
    private const TABLE = 'haerriz_agentic_idempotency';

    public function __construct(
        private ResourceConnection $resource,
        private Config $config
    ) {}

    /**
     * Reserve an idempotency key before execution.
     *
     * @return array<string,mixed>|null Completed cached response, or null when this caller owns execution.
     */
    public function acquire(
        string $key,
        string $scope,
        string $toolName,
        string $requestHash,
        int $storeId = 0
    ): ?array {
        $key = mb_substr(trim($key), 0, 128);
        if ($key === '') {
            return null;
        }
        $scopeHash = hash('sha256', $scope);
        $keyHash = hash('sha256', $key);
        $requestHash = preg_match('/^[a-f0-9]{64}$/', $requestHash) ? $requestHash : hash('sha256', $requestHash);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $this->config->getIdempotencyTtl($storeId));

        $row = $this->find($storeId, $scopeHash, $keyHash);
        if ($row) {
            return $this->resolveExisting($row, $requestHash, $now, $expires);
        }

        try {
            $connection->insert($table, [
                'store_id' => $storeId,
                'scope_hash' => $scopeHash,
                'idempotency_key_hash' => $keyHash,
                'tool_name' => mb_substr($toolName, 0, 64),
                'request_hash' => $requestHash,
                'status' => 'processing',
                'response_json' => null,
                'expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return null;
        } catch (\Throwable) {
            // A concurrent process may have won the unique-key insert race. Re-read authoritative state.
            $row = $this->find($storeId, $scopeHash, $keyHash);
            if (!$row) {
                throw new LocalizedException(__('The request could not obtain an idempotency reservation. Please retry.'));
            }
            return $this->resolveExisting($row, $requestHash, $now, $expires);
        }
    }

    public function complete(string $key, string $scope, array $result, int $storeId = 0): void
    {
        $key = mb_substr(trim($key), 0, 128);
        if ($key === '') return;
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            [
                'status' => 'completed',
                'response_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'store_id = ?' => $storeId,
                'scope_hash = ?' => hash('sha256', $scope),
                'idempotency_key_hash = ?' => hash('sha256', $key),
                'status = ?' => 'processing',
            ]
        );
    }

    /**
     * Preserve a reservation after an exception when Magento may already have committed a side effect.
     * Retrying the same key fails closed until expiry rather than risking a duplicate mutation.
     */
    public function markUncertain(string $key, string $scope, int $storeId = 0): void
    {
        $key = mb_substr(trim($key), 0, 128);
        if ($key === '') return;
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['status' => 'uncertain', 'updated_at' => date('Y-m-d H:i:s')],
            [
                'store_id = ?' => $storeId,
                'scope_hash = ?' => hash('sha256', $scope),
                'idempotency_key_hash = ?' => hash('sha256', $key),
                'status = ?' => 'processing',
            ]
        );
    }

    public function abandon(string $key, string $scope, int $storeId = 0): void
    {
        $key = mb_substr(trim($key), 0, 128);
        if ($key === '') return;
        $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            [
                'store_id = ?' => $storeId,
                'scope_hash = ?' => hash('sha256', $scope),
                'idempotency_key_hash = ?' => hash('sha256', $key),
                'status = ?' => 'processing',
            ]
        );
    }

    /** Backwards-compatible read used by older integrations. */
    public function get(string $key, string $scope, int $storeId = 0): ?array
    {
        $key = mb_substr(trim($key), 0, 128);
        if ($key === '') return null;
        $row = $this->find($storeId, hash('sha256', $scope), hash('sha256', $key));
        if (!$row || (string)($row['status'] ?? '') !== 'completed' || strtotime((string)$row['expires_at']) <= time()) return null;
        $data = json_decode((string)($row['response_json'] ?? ''), true);
        return is_array($data) ? $data : null;
    }

    /** Backwards-compatible completion alias. */
    public function save(string $key, string $scope, array $result, int $storeId = 0): void
    {
        $this->complete($key, $scope, $result, $storeId);
    }

    public function fingerprint(array $payload): string
    {
        $canonical = $this->canonicalize($payload);
        return hash('sha256', (string)json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as $key => $item) $value[$key] = $this->canonicalize($item);
        return $value;
    }

    private function find(int $storeId, string $scopeHash, string $keyHash): ?array
    {
        $row = $this->resource->getConnection()->fetchRow(
            $this->resource->getConnection()->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->where('store_id = ?', $storeId)
                ->where('scope_hash = ?', $scopeHash)
                ->where('idempotency_key_hash = ?', $keyHash)
                ->limit(1)
        );
        return is_array($row) ? $row : null;
    }

    private function resolveExisting(array $row, string $requestHash, string $now, string $newExpiry): ?array
    {
        if (!hash_equals((string)($row['request_hash'] ?? ''), $requestHash)) {
            throw new LocalizedException(__('That idempotency key was already used for a different request.'));
        }
        $expired = strtotime((string)($row['expires_at'] ?? '')) <= time();
        if (!$expired && (string)($row['status'] ?? '') === 'completed') {
            $data = json_decode((string)($row['response_json'] ?? ''), true);
            return is_array($data) ? $data : [];
        }
        if (!$expired && (string)($row['status'] ?? '') === 'processing') {
            throw new LocalizedException(__('That request is already being processed.'));
        }
        if (!$expired && (string)($row['status'] ?? '') === 'uncertain') {
            throw new LocalizedException(__('The previous attempt has an uncertain outcome. Check the current storefront state before retrying with a new idempotency key.'));
        }

        // Expired keys may be reused only for the exact same request fingerprint.
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            [
                'status' => 'processing',
                'response_json' => null,
                'expires_at' => $newExpiry,
                'updated_at' => $now,
            ],
            ['idempotency_id = ?' => (int)$row['idempotency_id']]
        );
        return null;
    }
}
