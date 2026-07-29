<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Shopping\Merchant\Products\V1beta\ProductsServiceClient;
use Google\Shopping\Merchant\DataSources\V1beta\DataSourcesServiceClient;
use Psr\Log\LoggerInterface;

class MerchantClientV1
{
    const XML_PATH_MERCHANT_ID = 'haerriz_googleshoppingfeed/api/merchant_id';
    const XML_PATH_SERVICE_ACCOUNT_JSON = 'haerriz_googleshoppingfeed/api/service_account_json';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
    }

    /**
     * Get configured Merchant ID
     *
     * @return string
     */
    public function getMerchantId()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MERCHANT_ID);
    }

    /**
     * Initialize Products Service Client
     *
     * @return ProductsServiceClient
     * @throws \Exception
     */
    public function getProductsClient()
    {
        $jsonKey = $this->scopeConfig->getValue(self::XML_PATH_SERVICE_ACCOUNT_JSON);
        if (!$jsonKey) {
            throw new \Exception("Google Merchant API Service Account JSON is not configured.");
        }

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/merchantapi.products',
            json_decode($jsonKey, true)
        );

        return new ProductsServiceClient([
            'credentials' => $credentials
        ]);
    }

    /**
     * Initialize Data Sources Service Client
     *
     * @return DataSourcesServiceClient
     * @throws \Exception
     */
    public function getDataSourcesClient()
    {
        $jsonKey = $this->scopeConfig->getValue(self::XML_PATH_SERVICE_ACCOUNT_JSON);
        if (!$jsonKey) {
            throw new \Exception("Google Merchant API Service Account JSON is not configured.");
        }

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/merchantapi.products',
            json_decode($jsonKey, true)
        );

        return new DataSourcesServiceClient([
            'credentials' => $credentials
        ]);
    }

    /**
     * Test connection/permission settings
     *
     * @return bool
     */
    public function testConnection()
    {
        try {
            $client = $this->getDataSourcesClient();
            $parent = 'accounts/' . $this->getMerchantId();
            // Call simple API list data sources to verify permissions
            $client->listDataSources($parent);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Google Merchant Connection Test failed: " . $e->getMessage());
            return false;
        }
    }
}
