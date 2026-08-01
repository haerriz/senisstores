<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'haerriz_googleshoppingfeed/general/enable';
    public const XML_PATH_CLI_PHP_PATH = 'haerriz_googleshoppingfeed/general/cli_php_path';
    public const XML_PATH_MERCHANT_ID = 'haerriz_googleshoppingfeed/google_merchant_api/merchant_id';
    public const XML_PATH_SERVICE_ACCOUNT_JSON = 'haerriz_googleshoppingfeed/google_merchant_api/service_account_json';
    public const XML_PATH_TARGET_COUNTRY = 'haerriz_googleshoppingfeed/google_merchant_api/target_country';
    public const XML_PATH_TARGET_CURRENCY = 'haerriz_googleshoppingfeed/google_merchant_api/target_currency';
    public const XML_PATH_API_MODE = 'haerriz_googleshoppingfeed/google_merchant_api/api_mode';
    public const XML_PATH_DEBUG_LOGGING = 'haerriz_googleshoppingfeed/logging/debug';

    protected ScopeConfigInterface $scopeConfig;
    protected EncryptorInterface $encryptor;
    protected array $cache = [];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    public function isEnabled($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, $scopeType, $scopeCode);
    }

    public function getCliPhpPath($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): ?string
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CLI_PHP_PATH, $scopeType, $scopeCode);
    }

    public function getMerchantId($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): ?string
    {
        $cacheKey = "merchant_id_{$scopeType}_{$scopeCode}";
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->scopeConfig->getValue(self::XML_PATH_MERCHANT_ID, $scopeType, $scopeCode);
        }
        return $this->cache[$cacheKey];
    }

    public function getServiceAccountJson($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): ?string
    {
        $cacheKey = "service_account_{$scopeType}_{$scopeCode}";
        if (!isset($this->cache[$cacheKey])) {
            $encrypted = $this->scopeConfig->getValue(self::XML_PATH_SERVICE_ACCOUNT_JSON, $scopeType, $scopeCode);
            $this->cache[$cacheKey] = $encrypted ? $this->encryptor->decrypt($encrypted) : null;
        }
        return $this->cache[$cacheKey];
    }

    public function getTargetCountry($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): ?string
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TARGET_COUNTRY, $scopeType, $scopeCode);
    }

    public function getTargetCurrency($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): ?string
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TARGET_CURRENCY, $scopeType, $scopeCode);
    }

    public function getApiMode($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): string
    {
        return (string)($this->scopeConfig->getValue(self::XML_PATH_API_MODE, $scopeType, $scopeCode) ?: 'production');
    }

    public function isDebugLoggingEnabled($scopeCode = null, $scopeType = ScopeInterface::SCOPE_STORE): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_LOGGING, $scopeType, $scopeCode);
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
