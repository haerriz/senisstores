<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Magento\Framework\Exception\LocalizedException;

class AdapterPool
{
    /**
     * @var AdapterInterface[]
     */
    protected $adapters;

    /**
     * @param AdapterInterface[] $adapters
     */
    public function __construct(array $adapters = [])
    {
        $this->adapters = $adapters;
    }

    /**
     * Get storage adapter by code
     *
     * @param string $code
     * @return AdapterInterface
     * @throws LocalizedException
     */
    public function get($code)
    {
        if (!isset($this->adapters[$code])) {
            throw new LocalizedException(
                __('Storage adapter for type "%1" is not configured.', $code)
            );
        }
        return $this->adapters[$code];
    }
}
