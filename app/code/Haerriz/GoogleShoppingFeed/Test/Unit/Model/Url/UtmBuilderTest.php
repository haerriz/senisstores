<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Url;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\Product;

class UtmBuilderTest extends TestCase
{
    protected $utmBuilder;
    protected $profileMock;
    protected $productMock;

    protected function setUp(): void
    {
        $this->utmBuilder = new UtmBuilder();
        $this->profileMock = $this->createMock(FeedProfileInterface::class);
        $this->productMock = $this->createMock(Product::class);
    }

    public function testBuildUrlReturnsBaseUrlWhenDisabled()
    {
        $this->profileMock->expects($this->once())
            ->method('getUtmEnabled')
            ->willReturn(0);

        $url = 'https://example.com/product.html';
        $this->assertEquals($url, $this->utmBuilder->buildUrl($url, $this->profileMock, $this->productMock));
    }

    public function testBuildUrlAppendsParametersAndResolvesPlaceholders()
    {
        $this->profileMock->expects($this->once())
            ->method('getUtmEnabled')
            ->willReturn(1);

        $this->profileMock->expects($this->any())
            ->method('getData')
            ->willReturnMap([
                [FeedProfileInterface::UTM_SOURCE, null, 'google'],
                [FeedProfileInterface::UTM_MEDIUM, null, 'cpc'],
                [FeedProfileInterface::UTM_CAMPAIGN, null, '{sku}-campaign']
            ]);

        $this->productMock->expects($this->any())
            ->method('getSku')
            ->willReturn('TEST-SKU');

        $url = 'https://example.com/product.html?existing=1#hash';
        $compiled = $this->utmBuilder->buildUrl($url, $this->profileMock, $this->productMock);

        $this->assertStringContainsString('utm_source=google', $compiled);
        $this->assertStringContainsString('utm_medium=cpc', $compiled);
        $this->assertStringContainsString('utm_campaign=TEST-SKU-campaign', $compiled);
        $this->assertStringContainsString('existing=1', $compiled);
        $this->assertStringEndsWith('#hash', $compiled);
    }
}
