<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resilience;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\App\CacheInterface;

/**
 * Soft shared-cache budget guard for external AI traffic.
 *
 * This is deliberately a load/cost shedder, not an authorization primitive. Deployments requiring
 * strict billing quotas can replace/augment it through a provider gateway while ToolPolicy remains
 * the security boundary.
 */
class ProviderBudgetGuard
{
    private const PREFIX = 'HAERRIZ_AGENTIC_PROVIDER_BUDGET_';

    public function __construct(private CacheInterface $cache, private Config $config) {}

    public function consume(string $provider, int $storeId = 0): bool
    {
        $limit = $this->config->getProviderRequestsPerMinute($storeId);
        if ($limit <= 0) return true;
        $bucket = gmdate('YmdHi');
        $key = self::PREFIX . $storeId . '_' . sha1($provider) . '_' . $bucket;
        $current = (int)($this->cache->load($key) ?: 0);
        if ($current >= $limit) return false;
        $this->cache->save((string)($current + 1), $key, [], 120);
        return true;
    }
}
