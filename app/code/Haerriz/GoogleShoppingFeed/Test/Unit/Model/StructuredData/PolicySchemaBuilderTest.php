<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\StructuredData;

use Haerriz\GoogleShoppingFeed\Model\StructuredData\PolicySchemaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PolicySchemaBuilderTest extends TestCase
{
    private PolicySchemaBuilder $builder;

    protected function setUp(): void
    {
        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseUrl'])
            ->getMock();
        $store->method('getBaseUrl')->willReturn('https://senisstores.com/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('general/store_information/name', ScopeInterface::SCOPE_STORE)
            ->willReturn('Seni S Stores');

        $this->builder = new PolicySchemaBuilder($storeManager, $scopeConfig);
    }

    public function testBuildsTruthfulVariableRateShippingPolicy(): void
    {
        $schema = $this->builder->build(PolicySchemaBuilder::TYPE_SHIPPING);
        $service = $schema['hasShippingService'];

        $this->assertSame('Seni S Stores', $schema['name']);
        $this->assertSame(
            'https://senisstores.com/ship-and-delivery-policy#standard',
            $service['@id']
        );
        $this->assertSame('IN', $service['shippingConditions']['shippingDestination']['addressCountry']);
        $this->assertSame(10, $service['shippingConditions']['transitTime']['duration']['maxValue']);
        $this->assertArrayNotHasKey('shippingRate', $service['shippingConditions']);
    }

    public function testBuildsNoReturnsPolicy(): void
    {
        $schema = $this->builder->build(PolicySchemaBuilder::TYPE_RETURN);
        $policy = $schema['hasMerchantReturnPolicy'];

        $this->assertSame('IN', $policy['applicableCountry']);
        $this->assertSame(
            'https://schema.org/MerchantReturnNotPermitted',
            $policy['returnPolicyCategory']
        );
        $this->assertSame('https://senisstores.com/refund-policy#policy', $policy['@id']);
        $this->assertArrayNotHasKey('merchantReturnDays', $policy);
    }

    public function testRejectsUnknownPolicyType(): void
    {
        $this->assertSame([], $this->builder->build('unknown'));
    }
}
