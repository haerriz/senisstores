<?php
namespace Haerriz\GoogleShoppingFeed\Model\Generation;

use Magento\Framework\App\CacheInterface;

class ProfileLock
{
    private const TTL = 1800;
    private const PREFIX = 'haerriz_gsf_profile_lock_';

    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function acquire(int $profileId): bool
    {
        $key = self::PREFIX . $profileId;
        if ($this->cache->load($key)) {
            return false;
        }
        $this->cache->save('1', $key, [], self::TTL);
        return true;
    }

    public function release(int $profileId): void
    {
        $this->cache->remove(self::PREFIX . $profileId);
    }

    /** @deprecated Use acquire() */
    public function lock(int $profileId): bool
    {
        return $this->acquire($profileId);
    }

    /** @deprecated Use release() */
    public function unlock(int $profileId): void
    {
        $this->release($profileId);
    }
}
