<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Conversation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;

class ConversationRepository
{
    private const CONVERSATION_TABLE = 'haerriz_agentic_conversation';
    private const MESSAGE_TABLE = 'haerriz_agentic_message';

    public function __construct(
        private ResourceConnection $resource,
        private Random $random
    ) {
    }

    public function start(array $identity, ?string $title = null): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $publicId = $this->random->getRandomString(40);
        $now = gmdate('Y-m-d H:i:s');
        $connection->insert($table, [
            'public_id' => $publicId,
            'store_id' => (int)$identity['store_id'],
            'customer_id' => (int)$identity['customer_id'] > 0 ? (int)$identity['customer_id'] : null,
            'client_id' => (string)$identity['client_id'],
            'title' => $this->sanitizeTitle($title ?: 'New conversation'),
            'status' => 'active',
            'context_json' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_message_at' => $now,
        ]);
        return $this->get($publicId, $identity);
    }

    public function getOrStart(?string $publicId, array $identity): array
    {
        if ($publicId) {
            $existing = $this->get($publicId, $identity, false);
            if ($existing !== null && ($existing['status'] ?? '') === 'active') {
                return $existing;
            }
        }
        return $this->start($identity);
    }

    public function get(string $publicId, array $identity, bool $throw = true): ?array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('public_id = ?', $publicId)->where('store_id = ?', (int)$identity['store_id'])
        );
        if (!$row) {
            if ($throw) {
                throw new LocalizedException(__('The conversation does not exist.'));
            }
            return null;
        }
        if (!$this->isOwnedBy($row, $identity)) {
            if ($throw) {
                throw new AuthorizationException(__('You are not allowed to access this conversation.'));
            }
            return null;
        }
        $row['context'] = $this->decode((string)($row['context_json'] ?? ''));
        return $row;
    }

    public function claimGuestConversations(array $identity): void
    {
        if ((int)$identity['customer_id'] <= 0 || empty($identity['client_id'])) {
            return;
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $connection->update($table, [
            'customer_id' => (int)$identity['customer_id'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], [
            'store_id = ?' => (int)$identity['store_id'],
            'client_id = ?' => (string)$identity['client_id'],
            'customer_id IS NULL',
        ]);
    }

    public function appendMessage(int $conversationId, string $role, string $content, array $payload = []): int
    {
        $role = in_array($role, ['user', 'assistant', 'system', 'tool'], true) ? $role : 'assistant';
        $connection = $this->resource->getConnection();
        $messageTable = $this->resource->getTableName(self::MESSAGE_TABLE);
        $conversationTable = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $now = gmdate('Y-m-d H:i:s');
        $connection->insert($messageTable, [
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => mb_substr(trim($content), 0, 65535),
            'payload_json' => $payload !== [] ? $this->encode($payload) : null,
            'created_at' => $now,
        ]);
        $connection->update($conversationTable, [
            'updated_at' => $now,
            'last_message_at' => $now,
        ], ['conversation_id = ?' => $conversationId]);
        return (int)$connection->lastInsertId($messageTable);
    }

    public function updateContext(int $conversationId, array $context): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $connection->update($table, [
            'context_json' => $this->encode($context),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['conversation_id = ?' => $conversationId]);
    }

    public function updateTitleIfNew(int $conversationId, string $message): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $connection->update($table, ['title' => $this->sanitizeTitle($message)], [
            'conversation_id = ?' => $conversationId,
            'title = ?' => 'New conversation',
        ]);
    }

    public function list(array $identity, int $limit = 20, int $page = 1): array
    {
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $select = $connection->select()->from($table, [
            'public_id', 'title', 'status', 'created_at', 'updated_at', 'last_message_at'
        ])->where('store_id = ?', (int)$identity['store_id']);
        if ((int)$identity['customer_id'] > 0) {
            $select->where('customer_id = ?', (int)$identity['customer_id']);
        } else {
            $select->where('customer_id IS NULL')->where('client_id = ?', (string)$identity['client_id']);
        }
        $select->order('last_message_at DESC')->limitPage($page, $limit);
        return array_map(static function (array $row): array {
            return [
                'id' => (string)$row['public_id'],
                'title' => (string)$row['title'],
                'status' => (string)$row['status'],
                'created_at' => (string)$row['created_at'],
                'updated_at' => (string)$row['updated_at'],
                'last_message_at' => (string)$row['last_message_at'],
            ];
        }, $connection->fetchAll($select));
    }

    public function messages(string $publicId, array $identity, int $limit = 100): array
    {
        $conversation = $this->get($publicId, $identity);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::MESSAGE_TABLE);
        $limit = max(1, min(200, $limit));
        $select = $connection->select()->from($table)->where('conversation_id = ?', (int)$conversation['conversation_id'])
            ->order('message_id DESC')->limit($limit);
        $rows = array_reverse($connection->fetchAll($select));
        return array_map(fn(array $row): array => [
            'id' => (string)$row['message_id'],
            'role' => (string)$row['role'],
            'content' => (string)$row['content'],
            'payload' => $this->decode((string)($row['payload_json'] ?? '')),
            'created_at' => (string)$row['created_at'],
        ], $rows);
    }

    public function close(string $publicId, array $identity): bool
    {
        $conversation = $this->get($publicId, $identity);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        return $connection->update($table, [
            'status' => 'closed',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['conversation_id = ?' => (int)$conversation['conversation_id']]) > 0;
    }

    public function deleteOlderThan(string $cutoff, ?bool $guestsOnly = null, ?int $storeId = null): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::CONVERSATION_TABLE);
        $where = $connection->quoteInto('last_message_at < ?', $cutoff);
        if ($guestsOnly === true) {
            $where .= ' AND customer_id IS NULL';
        } elseif ($guestsOnly === false) {
            $where .= ' AND customer_id IS NOT NULL';
        }
        if ($storeId !== null) {
            $where .= ' AND ' . $connection->quoteInto('store_id = ?', $storeId);
        }
        return $connection->delete($table, $where);
    }

    private function isOwnedBy(array $row, array $identity): bool
    {
        $ownerCustomerId = (int)($row['customer_id'] ?? 0);
        $customerId = (int)($identity['customer_id'] ?? 0);
        if ($ownerCustomerId > 0) {
            return $customerId > 0 && $ownerCustomerId === $customerId;
        }
        return $customerId === 0 && hash_equals((string)($row['client_id'] ?? ''), (string)($identity['client_id'] ?? ''));
    }

    private function sanitizeTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?? $title);
        return mb_substr($title !== '' ? $title : 'New conversation', 0, 120);
    }

    private function encode(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function decode(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
