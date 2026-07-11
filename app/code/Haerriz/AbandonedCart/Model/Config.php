<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'haerriz_abandonedcart/general/enabled';
    private const XML_PATH_ABANDON_HOURS = 'haerriz_abandonedcart/general/abandon_after_hours';
    private const XML_PATH_MAX_AGE_DAYS = 'haerriz_abandonedcart/general/max_cart_age_days';
    private const XML_PATH_BATCH_SIZE = 'haerriz_abandonedcart/general/batch_size';
    private const XML_PATH_DELAY_MIN = 'haerriz_abandonedcart/general/delay_min_seconds';
    private const XML_PATH_DELAY_MAX = 'haerriz_abandonedcart/general/delay_max_seconds';
    private const XML_PATH_MAX_PRODUCTS = 'haerriz_abandonedcart/general/max_products_in_email';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getAbandonAfterHours($storeId = null)
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_ABANDON_HOURS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getMaxCartAgeDays($storeId = null)
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_AGE_DAYS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getBatchSize($storeId = null)
    {
        return max(1, min(10, (int) $this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE, ScopeInterface::SCOPE_STORE, $storeId)));
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getDelayMinSeconds($storeId = null)
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_DELAY_MIN, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getDelayMaxSeconds($storeId = null)
    {
        $min = $this->getDelayMinSeconds($storeId);
        return max($min, (int) $this->scopeConfig->getValue(self::XML_PATH_DELAY_MAX, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getMaxProductsInEmail($storeId = null)
    {
        return max(1, min(8, (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_PRODUCTS, ScopeInterface::SCOPE_STORE, $storeId)));
    }
}
