<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const XML_PATH_ENABLED = 'haerriz_googleshoppingfeed/general/enabled';
    const XML_PATH_MERCHANT_ID = 'haerriz_googleshoppingfeed/api/merchant_id';
    const XML_PATH_SERVICE_ACCOUNT_JSON = 'haerriz_googleshoppingfeed/api/service_account_json';
    const XML_PATH_DEBUG_LOGGING = 'haerriz_googleshoppingfeed/logging/debug';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Check if module is enabled
     *
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function isEnabled($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, $scopeType, $scopeCode);
    }

    /**
     * Get Google Merchant Center ID
     *
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return string|null
     */
    public function getMerchantId($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        $cacheKey = "merchant_id_{$scopeType}_{$scopeCode}";
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->scopeConfig->getValue(self::XML_PATH_MERCHANT_ID, $scopeType, $scopeCode);
        }
        return $this->cache[$cacheKey];
    }

    /**
     * Get Decrypted Service Account JSON
     *
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return string|null
     */
    public function getServiceAccountJson($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        $cacheKey = "service_account_{$scopeType}_{$scopeCode}";
        if (!isset($this->cache[$cacheKey])) {
            $encrypted = $this->scopeConfig->getValue(self::XML_PATH_SERVICE_ACCOUNT_JSON, $scopeType, $scopeCode);
            $this->cache[$cacheKey] = $encrypted ? $this->encryptor->decrypt($encrypted) : null;
        }
        return $this->cache[$cacheKey];
    }

    /**
     * Check if debug logging is enabled
     *
     * @param string|int|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function isDebugLoggingEnabled($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_LOGGING, $scopeType, $scopeCode);
    }
}
