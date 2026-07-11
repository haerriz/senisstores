<?php
namespace Haerriz\Shiprocket\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;

class Shiprocket extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'shiprocket';
    protected $_isFixed = false;

    private $rateResultFactory;
    protected $rateMethodFactory;
    private $httpClient;
    private $cache;
    private $logger;
    private $configWriter;
    private $trackResultFactory;
    private $trackStatusFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\HTTP\Client\Curl $httpClient,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackResultFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
        $this->trackResultFactory = $trackResultFactory;
        $this->trackStatusFactory = $trackStatusFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get tracking info
     *
     * @param string $tracking
     * @return \Magento\Shipping\Model\Tracking\Result\Status|bool
     */
    public function getTrackingInfo($tracking)
    {
        $status = $this->trackStatusFactory->create();
        $status->setCarrier($this->_code);
        $status->setCarrierTitle($this->getConfigData('title'));
        $status->setTracking($tracking);
        $status->setPopup(true);
        $status->setUrl('https://track.shiprocket.in/' . $tracking);
        return $status;
    }

    public function collectRates(RateRequest $request)
    {
        $this->logger->info('Shiprocket: collectRates called. Postcode: ' . $request->getDestPostcode() . ', Country: ' . $request->getDestCountryId() . ', Weight: ' . $request->getPackageWeight() . ', Qty: ' . $request->getPackageQty());
        
        if (!$this->getConfigData('active')) {
            $this->logger->info('Shiprocket: carrier is not active.');
            return false;
        }

        $destPostcode = $request->getDestPostcode();
        if (empty($destPostcode)) {
            $this->logger->info('Shiprocket: postcode is empty.');
            return false;
        }

        // Sanitize and validate Indian Pincode (must be exactly 6 digits)
        $destPostcode = preg_replace('/[^0-9]/', '', $destPostcode);
        if (strlen($destPostcode) !== 6) {
            return false;
        }

        // Get origin postcode
        $pickupPostcode = $this->_scopeConfig->getValue('shipping/origin/postcode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $pickupPostcode = preg_replace('/[^0-9]/', '', $pickupPostcode);
        if (strlen($pickupPostcode) !== 6) {
            $pickupPostcode = '625531'; // Fallback to Seni's Stores postcode
        }

        // Get credentials
        $apiEmail = $this->getConfigData('api_email');
        $apiPassword = $this->getConfigData('api_password');
        if (empty($apiEmail) || empty($apiPassword)) {
            $this->logger->error('Shiprocket: API Email or Password is not configured.');
            return false;
        }

        // 1. Authenticate with Shiprocket
        $token = $this->getApiToken($apiEmail, $apiPassword);
        if (!$token) {
            $this->logger->error('Shiprocket: Failed to authenticate.');
            return false;
        }

        // 2. Compute packed dimensions and actual weight
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;
        $totalWeight = 0;

        $defaultLength = (float)$this->getConfigData('default_length') ?: 10.0;
        $defaultWidth = (float)$this->getConfigData('default_width') ?: 10.0;
        $defaultHeight = (float)$this->getConfigData('default_height') ?: 10.0;

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProductType() == 'virtual' || $item->getParentItem()) {
                    continue;
                }

                $product = $item->getProduct();
                if (!$product) {
                    continue;
                }

                // Load product to get attributes if not present
                $length = (float)$product->getData('shipping_length');
                $width = (float)$product->getData('shipping_width');
                $height = (float)$product->getData('shipping_height');

                // Fallback to defaults if not set
                if ($length <= 0) $length = $defaultLength;
                if ($width <= 0) $width = $defaultWidth;
                if ($height <= 0) $height = $defaultHeight;

                $qty = $item->getQty();
                $maxLength = max($maxLength, $length);
                $maxWidth = max($maxWidth, $width);
                $totalHeight += ($height * $qty);

                $weight = (float)$item->getWeight();
                $totalWeight += ($weight * $qty);
            }
        }

        if ($totalWeight <= 0) {
            $totalWeight = 0.5; // Minimum weight for rate calculation
        }

        // Add 2cm buffer to shipping box dimensions
        $boxLength = $maxLength + 2.0;
        $boxWidth = $maxWidth + 2.0;
        $boxHeight = $totalHeight + 2.0;

        // 3. Make Serviceability Call
        $result = $this->rateResultFactory->create();
        $this->logger->info('Shiprocket: Requesting serviceability with Weight: ' . $totalWeight . ', L: ' . $boxLength . ', W: ' . $boxWidth . ', H: ' . $boxHeight);
        $couriers = $this->getCourierRates($token, $pickupPostcode, $destPostcode, $totalWeight, $boxLength, $boxWidth, $boxHeight);

        $this->logger->info('Shiprocket: Received ' . count($couriers) . ' couriers from API.');

        if (empty($couriers)) {
            $this->logger->info('Shiprocket: No couriers found/returned.');
            return $result;
        }

        foreach ($couriers as $courier) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));

            // Method code includes courier ID to make it unique
            $methodCode = 'courier_' . $courier['id'];
            $method->setMethod($methodCode);

            // Display title: e.g. "Delhivery Surface (Expected Delivery: 4 Days)"
            $methodTitle = $courier['name'];
            if (!empty($courier['etd'])) {
                $methodTitle .= ' (Estimated Delivery: ' . $courier['etd'] . ')';
            }
            $method->setMethodTitle($methodTitle);

            // Rate returned from Shiprocket
            $shippingPrice = (float)$courier['rate'];
            $method->setPrice($shippingPrice);
            $method->setCost($shippingPrice);

            $result->append($method);
        }

        return $result;
    }

    private function getApiToken($email, $password)
    {
        $cacheKey = 'shiprocket_auth_token_hash';
        $token = $this->cache->load($cacheKey);
        if ($token) {
            return $token;
        }

        $apiUrl = $this->getConfigData('api_url') . '/auth/login';
        $params = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        try {
            $this->httpClient->setOption(CURLOPT_TIMEOUT, 15);
            $this->httpClient->addHeader('Content-Type', 'application/json');
            $this->httpClient->post($apiUrl, $params);
            
            $response = json_decode($this->httpClient->getBody(), true);
            if (isset($response['token'])) {
                $token = $response['token'];
                // Cache token for 11 days (Shiprocket tokens expire in 12 days)
                $this->cache->save($token, $cacheKey, [], 11 * 24 * 3600);
                return $token;
            } else {
                $this->logger->error('Shiprocket Login API error response: ' . $this->httpClient->getBody());
            }
        } catch (\Exception $e) {
            $this->logger->error('Shiprocket Login Exception: ' . $e->getMessage());
        }

        return false;
    }

    private function getCourierRates($token, $pickup, $delivery, $weight, $length, $width, $height)
    {
        $apiUrl = $this->getConfigData('api_url') . '/courier/serviceability/';
        
        // Build query string
        $queryParams = http_build_query([
            'pickup_postcode' => $pickup,
            'delivery_postcode' => $delivery,
            'weight' => $weight,
            'cod' => 0,
            'length' => ceil($length),
            'width' => ceil($width),
            'height' => ceil($height)
        ]);

        $fullUrl = $apiUrl . '?' . $queryParams;

        try {
            $this->httpClient->setOption(CURLOPT_TIMEOUT, 15);
            $this->httpClient->addHeader('Authorization', 'Bearer ' . $token);
            $this->httpClient->addHeader('Content-Type', 'application/json');
            $this->httpClient->get($fullUrl);

            $response = json_decode($this->httpClient->getBody(), true);
            
            if (isset($response['data']['available_courier_companies']) && is_array($response['data']['available_courier_companies'])) {
                $couriers = [];
                foreach ($response['data']['available_courier_companies'] as $c) {
                    if (isset($c['rate'])) {
                        $couriers[] = [
                            'id' => $c['courier_company_id'],
                            'name' => $c['courier_name'],
                            'rate' => $c['rate'],
                            'etd' => isset($c['etd']) ? $c['etd'] : ''
                        ];
                    }
                }
                return $couriers;
            } else {
                $this->logger->info('Shiprocket Serviceability API info: ' . $this->httpClient->getBody());
            }
        } catch (\Exception $e) {
            $this->logger->error('Shiprocket Serviceability Exception: ' . $e->getMessage());
        }

        return [];
    }
}
