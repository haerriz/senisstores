<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Learning;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\App\ResourceConnection;

/**
 * Safe adaptive routing. It learns exact privacy-normalized aliases/outcomes, not model weights,
 * executable code or permissions.
 *
 * Only a deliberately small set of public, read-only discovery/support tools may auto-activate.
 * Customer-sensitive reads and all mutations can be observed for analytics but are never promoted
 * into an automatic learned route.
 */
class AdaptiveLearningService
{
    private const AUTO_ROUTE_TOOLS = [
        'get_store_information',
        'answer_store_question',
        'get_store_context',
        'get_catalog_navigation',
        'search_pages',
        'search_categories',
        'search_products',
    ];

    public function __construct(
        private Config $config,
        private ResourceConnection $resource,
        private PhraseNormalizer $normalizer,
        private ToolPolicy $toolPolicy
    ) {}

    public function observe(string $message, string $toolName, bool $success, array $identity = []): void
    {
        $storeId = (int)($identity['store_id'] ?? 0);
        if (!$this->config->isAdaptiveLearningEnabled($storeId) || $toolName === '') {
            return;
        }
        $pattern = $this->normalizer->normalize($message);
        if ($pattern === '' || mb_strlen($pattern) < 3) {
            return;
        }

        $hash = hash('sha256', $pattern);
        $table = $this->resource->getTableName('haerriz_agentic_learning_pattern');
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($table)
                ->where('store_id = ?', $storeId)
                ->where('pattern_hash = ?', $hash)
                ->where('tool_name = ?', $toolName)
        );

        $hits = (int)($row['hits'] ?? 0) + 1;
        $successes = (int)($row['successes'] ?? 0) + ($success ? 1 : 0);
        $failures = (int)($row['failures'] ?? 0) + ($success ? 0 : 1);
        $confidence = $hits > 0 ? $successes / $hits : 0.0;
        $data = [
            'store_id' => $storeId,
            'pattern_hash' => $hash,
            'pattern_text' => $pattern,
            'tool_name' => $toolName,
            'hits' => $hits,
            'successes' => $successes,
            'failures' => $failures,
            'confidence' => round($confidence, 4),
            // Reconciliation below is the only place that can activate a route.
            'status' => 'observed',
            'auto_approved' => 0,
        ];
        if ($row) {
            $connection->update($table, $data, ['pattern_id = ?' => (int)$row['pattern_id']]);
        } else {
            $connection->insert($table, $data);
        }
        $this->reconcile($storeId, $hash);
    }

    public function learnedPlan(string $message, array $context = []): ?array
    {
        $storeId = (int)($context['identity']['store_id'] ?? 0);
        if (!$this->config->isAdaptiveLearningEnabled($storeId)) {
            return null;
        }
        $pattern = $this->normalizer->normalize($message);
        if ($pattern === '') {
            return null;
        }
        $table = $this->resource->getTableName('haerriz_agentic_learning_pattern');
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($table)
                ->where('store_id = ?', $storeId)
                ->where('pattern_hash = ?', hash('sha256', $pattern))
                ->where('status = ?', 'active')
                ->where('auto_approved = ?', 1)
                ->order('confidence DESC')
                ->limit(2)
        );
        // Default-safe: an exact phrase must map to one unambiguous active route only.
        if (count($rows) !== 1) {
            return null;
        }
        $row = $rows[0];
        $tool = (string)$row['tool_name'];
        if (!in_array($tool, self::AUTO_ROUTE_TOOLS, true)) {
            return null;
        }
        $meta = $this->toolPolicy->metadata($tool, $storeId);
        if (!empty($meta['mutates_state']) || !empty($meta['requires_customer'])) {
            return null;
        }
        $args = match ($tool) {
            'get_store_information' => ['topic' => $message],
            'answer_store_question' => ['query' => $message, 'limit' => 3],
            'search_pages' => ['query' => $message, 'limit' => 5],
            'search_categories' => ['query' => $message, 'limit' => 5],
            'search_products' => [
                'phrase' => $message,
                'filters' => [],
                'sort' => [],
                'page_size' => $this->config->getPageSize($storeId),
                'current_page' => 1,
            ],
            'get_catalog_navigation' => ['limit' => 20],
            default => [],
        };
        return [
            'assistant_message' => '',
            'tools' => [['name' => $tool, 'arguments' => $args]],
            'learning_hint' => ['tool' => $tool, 'confidence' => (float)$row['confidence']],
        ];
    }

    public function feedback(string $message, string $toolName, int $rating, array $identity = []): void
    {
        if ($rating === 0) {
            return;
        }
        $this->observe($message, $toolName, $rating > 0, $identity);
    }

    public function prune(?int $storeId = null): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('haerriz_agentic_learning_pattern');
        $days = $this->config->getLearningRetentionDays($storeId);
        $cutoff = (new \DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s');
        // Active routes are retained while they remain configured; observed/conflicting stale data ages out.
        return $connection->delete($table, ['updated_at < ?' => $cutoff, 'status != ?' => 'active']);
    }

    private function reconcile(int $storeId, string $hash): void
    {
        $table = $this->resource->getTableName('haerriz_agentic_learning_pattern');
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($table)
                ->where('store_id = ?', $storeId)
                ->where('pattern_hash = ?', $hash)
        );
        if ($rows === []) {
            return;
        }

        $minimum = $this->config->getLearningMinSuccesses($storeId);
        $threshold = $this->config->getLearningConfidenceThreshold($storeId);
        $eligible = [];
        $credibleCompetitors = [];
        foreach ($rows as $row) {
            $tool = (string)($row['tool_name'] ?? '');
            $successes = (int)($row['successes'] ?? 0);
            $confidence = (float)($row['confidence'] ?? 0.0);
            $meta = $this->toolPolicy->metadata($tool, $storeId);
            $safePublicRead = in_array($tool, self::AUTO_ROUTE_TOOLS, true)
                && empty($meta['mutates_state'])
                && empty($meta['requires_customer']);
            if ($safePublicRead && $this->config->isAutoLearningReadOnlyEnabled($storeId)
                && $successes >= $minimum && $confidence >= $threshold) {
                $eligible[] = (int)$row['pattern_id'];
            }
            // Two meaningfully successful tools for the same exact normalized phrase are ambiguous.
            if ($successes >= 2 && $confidence >= 0.60) {
                $credibleCompetitors[] = (int)$row['pattern_id'];
            }
        }

        $activate = count($eligible) === 1 && count($credibleCompetitors) <= 1 ? $eligible[0] : null;
        foreach ($rows as $row) {
            $id = (int)$row['pattern_id'];
            $status = $activate === $id ? 'active' : (count($credibleCompetitors) > 1 ? 'conflict' : 'observed');
            $connection->update($table, [
                'status' => $status,
                'auto_approved' => $activate === $id ? 1 : 0,
            ], ['pattern_id = ?' => $id]);
        }
    }
}
