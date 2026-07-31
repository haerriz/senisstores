<?php
namespace Haerriz\GoogleShoppingFeed\Model\Artifact;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class CurrentArtifactPublisher
{
    private const CACHE_TAG    = 'haerriz_gsfeed_artifact';
    private const CACHE_LIFETIME = 86400; // 24h

    private $cache;
    private $logger;

    public function __construct(
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->cache  = $cache;
        $this->logger = $logger;
    }

    /**
     * Store the current artifact path pointer in Magento cache
     * so Download controller can resolve it instantly.
     */
    public function publish(FeedProfileInterface $profile, string $absolutePath): void
    {
        try {
            $cacheKey = self::CACHE_TAG . '_' . $profile->getId();
            $this->cache->save($absolutePath, $cacheKey, [self::CACHE_TAG], self::CACHE_LIFETIME);
        } catch (\Exception $e) {
            $this->logger->debug("CurrentArtifactPublisher: " . $e->getMessage());
        }
    }

    /**
     * Retrieve the current artifact path from cache.
     */
    public function resolve(int $profileId): ?string
    {
        try {
            $cacheKey = self::CACHE_TAG . '_' . $profileId;
            $path = $this->cache->load($cacheKey);
            return $path ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
