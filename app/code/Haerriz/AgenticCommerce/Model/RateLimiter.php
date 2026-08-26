<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class RateLimiter
{
    private const PREFIX = 'agentic_commerce_rate_';

    public function __construct(
        private CacheInterface $cache,
        private RemoteAddress $remoteAddress,
        private Config $config
    ) {
    }

    /**
     * Apply both a shopper/client bucket and a broader IP bucket.
     *
     * The IP bucket prevents an anonymous caller from bypassing the configured limit merely by rotating
     * client IDs, while the higher aggregate allowance avoids penalising normal shared/NAT networks too quickly.
     */
    public function assertAllowed(?string $sessionId, int $storeId): void
    {
        $bucket = (int)floor(time() / 60);
        $ip = (string)($this->remoteAddress->getRemoteAddress() ?: 'unknown');
        $actor = trim((string)$sessionId) !== '' ? trim((string)$sessionId) : 'anonymous';
        $limit = $this->config->getRateLimit($storeId);

        $actorKey = self::PREFIX . 'actor_' . hash('sha256', $storeId . '|' . $ip . '|' . $actor . '|' . $bucket);
        $ipKey = self::PREFIX . 'ip_' . hash('sha256', $storeId . '|' . $ip . '|' . $bucket);

        $this->assertBucket($actorKey, $limit);
        $this->assertBucket($ipKey, max($limit, min(1500, $limit * 5)));
    }

    private function assertBucket(string $key, int $limit): void
    {
        $count = (int)$this->cache->load($key);
        if ($count >= $limit) {
            throw new LocalizedException(__('Too many assistant requests. Please try again shortly.'));
        }
        $this->cache->save((string)($count + 1), $key, [], 70);
    }
}
