<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Model\Logger\Sanitizer;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Shopping\Merchant\Products\V1\Client\ProductInputsServiceClient;
use Google\Shopping\Merchant\DataSources\V1\Client\DataSourcesServiceClient;
use Google\Shopping\Merchant\DataSources\V1\ListDataSourcesRequest;
use Psr\Log\LoggerInterface;

class MerchantClientV1
{
    public const XML_PATH_MERCHANT_ID = 'haerriz_googleshoppingfeed/google_merchant_api/merchant_id';
    public const XML_PATH_SERVICE_ACCOUNT_JSON = 'haerriz_googleshoppingfeed/google_merchant_api/service_account_json';

    protected ScopeConfigInterface $scopeConfig;
    protected CredentialProviderInterface $encryptor;
    private Sanitizer $sanitizer;
    protected LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CredentialProviderInterface $credentialProvider,
        LoggerInterface $logger,
        Sanitizer $sanitizer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $credentialProvider;
        $this->logger = $logger;
        $this->sanitizer = $sanitizer;
    }

    public function getMerchantId()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MERCHANT_ID);
    }

    public function getProductsClient()
    {
        return new ProductInputsServiceClient([
            'credentials' => $this->createCredentials()
        ]);
    }

    public function getDataSourcesClient()
    {
        return new DataSourcesServiceClient([
            'credentials' => $this->createCredentials()
        ]);
    }

    public function testConnection()
    {
        try {
            $client = $this->getDataSourcesClient();
            $parent = 'accounts/' . $this->getMerchantId();
            $request = new ListDataSourcesRequest();
            $request->setParent($parent);
            $client->listDataSources($request);
            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                $this->sanitizer->sanitize('Google Merchant connection test failed: ' . $e->getMessage())
            );
            return false;
        }
    }

    public function listProducts(string $merchantId): array
    {
        $parent = 'accounts/' . ltrim($merchantId, '/');
        if (!str_starts_with($parent, 'accounts/')) {
            $parent = 'accounts/' . $merchantId;
        }

        $productsServiceClass = '\\Google\\Shopping\\Merchant\\Products\\V1\\Client\\ProductsServiceClient';
        $listRequestClass = '\\Google\\Shopping\\Merchant\\Products\\V1\\ListProductsRequest';

        if (class_exists($productsServiceClass) && class_exists($listRequestClass)) {
            $client = new $productsServiceClass(['credentials' => $this->createCredentials()]);
            $request = new $listRequestClass();
            if (method_exists($request, 'setParent')) {
                $request->setParent($parent);
            }
            if (method_exists($client, 'listProducts')) {
                return $this->normalizeProductList($client->listProducts($request));
            }
        }

        $client = $this->getProductsClient();
        if (method_exists($client, 'listProducts')) {
            return $this->normalizeProductList($client->listProducts($parent));
        }
        if (method_exists($client, 'listProductInputs')) {
            $listInputsRequestClass = '\\Google\\Shopping\\Merchant\\Products\\V1\\ListProductInputsRequest';
            if (class_exists($listInputsRequestClass)) {
                $request = new $listInputsRequestClass();
                if (method_exists($request, 'setParent')) {
                    $request->setParent($parent);
                }
                return $this->normalizeProductList($client->listProductInputs($request));
            }
            return $this->normalizeProductList($client->listProductInputs($parent));
        }

        throw new \RuntimeException(
            'Installed Google Merchant Products client does not expose a product listing method. '
            . 'Install/update google/shopping-merchant-products and configure service account credentials.'
        );
    }

    public function batchInsertProducts(string $merchantId, array $products): array
    {
        if ($products === []) {
            return ['inserted' => 0];
        }

        $client = $this->getProductsClient();
        if (!method_exists($client, 'insertProductInput')) {
            throw new \RuntimeException('Installed Google Merchant Products client does not expose product insert support.');
        }

        $inserted = 0;
        foreach ($products as $product) {
            $client->insertProductInput('accounts/' . $merchantId, $product);
            $inserted++;
        }

        return ['inserted' => $inserted];
    }

    private function createCredentials(): ServiceAccountCredentials
    {
        return new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/content',
            $this->getServiceAccountKeyArray()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getServiceAccountKeyArray(): array
    {
        $candidates = [];

        $decrypted = trim((string)$this->encryptor->getConfigSecret(self::XML_PATH_SERVICE_ACCOUNT_JSON));
        if ($decrypted !== '') {
            $candidates[] = $decrypted;
        }

        // Fallback: value may have been saved as plain JSON (not encrypted).
        $raw = trim((string)$this->scopeConfig->getValue(self::XML_PATH_SERVICE_ACCOUNT_JSON));
        if ($raw !== '' && $raw !== $decrypted) {
            $candidates[] = $raw;
        }

        foreach ($candidates as $json) {
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (empty($decoded['client_email']) || empty($decoded['private_key'])) {
                continue;
            }
            return $decoded;
        }

        throw new \RuntimeException(
            'Service account JSON is missing or invalid. '
            . 'Paste the full Google Cloud service-account JSON (must include client_email and private_key) '
            . 'under Stores > Configuration > Haerriz Google Shopping Feed > Google Merchant API, then save config.'
        );
    }

    private function normalizeProductList($response): array
    {
        $products = [];
        $items = method_exists($response, 'iterateAllElements') ? $response->iterateAllElements() : $response;
        if (!is_iterable($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $products[] = $item;
                continue;
            }
            $products[] = [
                'offerId' => method_exists($item, 'getOfferId') ? (string)$item->getOfferId() : '',
                'status' => method_exists($item, 'getStatus') ? (string)$item->getStatus() : '',
                'approvalStatus' => method_exists($item, 'getApprovalStatus') ? (string)$item->getApprovalStatus() : '',
                'itemLevelIssues' => method_exists($item, 'getItemLevelIssues')
                    ? (array)$item->getItemLevelIssues()
                    : [],
            ];
        }

        return $products;
    }
}
