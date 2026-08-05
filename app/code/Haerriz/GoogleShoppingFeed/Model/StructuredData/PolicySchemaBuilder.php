<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Model\StructuredData;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class PolicySchemaBuilder
{
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_RETURN = 'return';

    private const STORE_NAME_CONFIG_PATH = 'general/store_information/name';
    private const SHIPPING_POLICY_PATH = 'ship-and-delivery-policy';
    private const RETURN_POLICY_PATH = 'refund-policy';

    private StoreManagerInterface $storeManager;

    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $policyType): array
    {
        if ($policyType === self::TYPE_SHIPPING) {
            return $this->buildShippingPolicy();
        }

        if ($policyType === self::TYPE_RETURN) {
            return $this->buildReturnPolicy();
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getOrganizationReference(): array
    {
        return [
            '@type' => 'OnlineStore',
            '@id' => $this->getOrganizationId(),
            'name' => $this->getStoreName(),
        ];
    }

    public function getOrganizationId(): string
    {
        return $this->getBaseUrl() . '#organization';
    }

    public function getShippingServiceId(): string
    {
        return $this->getBaseUrl() . self::SHIPPING_POLICY_PATH . '#standard';
    }

    public function getReturnPolicyId(): string
    {
        return $this->getBaseUrl() . self::RETURN_POLICY_PATH . '#policy';
    }

    public function getStoreName(): string
    {
        $storeName = trim((string) $this->scopeConfig->getValue(
            self::STORE_NAME_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        ));

        return $storeName !== '' ? $storeName : 'Online Store';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShippingPolicy(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            '@id' => $this->getOrganizationId(),
            'name' => $this->getStoreName(),
            'url' => $this->getBaseUrl(),
            'hasShippingService' => [
                '@type' => 'ShippingService',
                '@id' => $this->getShippingServiceId(),
                'name' => 'Courier Delivery',
                'description' => 'Shipping charges are calculated at checkout from order weight and destination.',
                'fulfillmentType' => 'https://schema.org/FulfillmentTypeDelivery',
                'handlingTime' => [
                    '@type' => 'ServicePeriod',
                    'duration' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 0,
                        'maxValue' => 7,
                        'unitCode' => 'DAY',
                    ],
                ],
                'shippingConditions' => [
                    '@type' => 'ShippingConditions',
                    'shippingDestination' => [
                        '@type' => 'DefinedRegion',
                        'addressCountry' => 'IN',
                    ],
                    'transitTime' => [
                        '@type' => 'ServicePeriod',
                        'duration' => [
                            '@type' => 'QuantitativeValue',
                            'maxValue' => 10,
                            'unitCode' => 'DAY',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReturnPolicy(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            '@id' => $this->getOrganizationId(),
            'name' => $this->getStoreName(),
            'url' => $this->getBaseUrl(),
            'hasMerchantReturnPolicy' => [
                '@type' => 'MerchantReturnPolicy',
                '@id' => $this->getReturnPolicyId(),
                'applicableCountry' => 'IN',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
                'merchantReturnLink' => $this->getBaseUrl() . self::RETURN_POLICY_PATH,
            ],
        ];
    }

    private function getBaseUrl(): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/';
    }
}
