<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

class ProfileConfigReader
{
    /**
     * Read an additive profile field without widening the public service contract prematurely.
     *
     * @param FeedProfileInterface $profile
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(FeedProfileInterface $profile, $key, $default = null)
    {
        if (!$profile instanceof FeedProfile) {
            return $default;
        }

        $value = $profile->getData($key);
        return $value === null || $value === '' ? $default : $value;
    }

    public function getBoolean(FeedProfileInterface $profile, $key, $default = false)
    {
        return (bool)$this->get($profile, $key, $default ? 1 : 0);
    }

    public function getIntList(FeedProfileInterface $profile, $key)
    {
        $value = $this->get($profile, $key, '');
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }
}
