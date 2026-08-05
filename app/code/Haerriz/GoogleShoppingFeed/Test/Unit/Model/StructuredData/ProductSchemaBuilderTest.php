<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\StructuredData;

use Haerriz\GoogleShoppingFeed\Model\StructuredData\PolicySchemaBuilder;
use Haerriz\GoogleShoppingFeed\Model\StructuredData\ProductSchemaBuilder;
use Magento\Catalog\Model\Product;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductSchemaBuilderTest extends TestCase
{
    private ProductSchemaBuilder $builder;

    private PolicySchemaBuilder&MockObject $policyBuilder;

    protected function setUp(): void
    {
        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseUrl', 'getCurrentCurrencyCode'])
            ->getMock();
        $store->method('getBaseUrl')->willReturnCallback(
            static function ($type = null): string {
                return $type === UrlInterface::URL_TYPE_MEDIA
                    ? 'https://senisstores.com/media/'
                    : 'https://senisstores.com/';
            }
        );
        $store->method('getCurrentCurrencyCode')->willReturn('INR');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->policyBuilder = $this->createMock(PolicySchemaBuilder::class);
        $this->policyBuilder->method('getOrganizationReference')->willReturn([
            '@type' => 'OnlineStore',
            '@id' => 'https://senisstores.com/#organization',
            'name' => 'Seni S Stores',
        ]);
        $this->policyBuilder->method('getShippingServiceId')
            ->willReturn('https://senisstores.com/ship-and-delivery-policy#standard');
        $this->policyBuilder->method('getReturnPolicyId')
            ->willReturn('https://senisstores.com/refund-policy#policy');

        $this->builder = new ProductSchemaBuilder($storeManager, $this->policyBuilder);
    }

    public function testBuildsCompleteOfferAndOmitsWhitespaceSkuAndFakeBrand(): void
    {
        $product = $this->createProduct([
            'image' => '/g/i/gi_wire_2.jpg',
            'small_image' => '/g/i/gi_wire_2.jpg',
            'thumbnail' => '/g/i/gi_wire_2.jpg',
            'description' => '<p>Galvanised <strong>wire</strong></p>',
            'rating_summary' => 92,
            'reviews_count' => 36,
        ]);
        $product->method('getName')->willReturn('10 g GI wire');
        $product->method('getSku')->willReturn('10 g GI wire');
        $product->method('getFinalPrice')->willReturn(122.0);
        $product->method('getProductUrl')->willReturn('https://senisstores.com/10-g-gi-wire.html');
        $product->method('isAvailable')->willReturn(true);
        $product->method('getAttributeText')->willReturn(null);

        $schema = $this->builder->build($product);
        $offer = $schema['offers'];

        $this->assertArrayNotHasKey('sku', $schema);
        $this->assertArrayNotHasKey('brand', $schema);
        $this->assertSame(['https://senisstores.com/media/catalog/product/g/i/gi_wire_2.jpg'], $schema['image']);
        $this->assertSame('Galvanised wire', $schema['description']);
        $this->assertSame('https://schema.org/InStock', $offer['availability']);
        $this->assertSame('INR', $offer['priceCurrency']);
        $this->assertSame(
            'https://senisstores.com/ship-and-delivery-policy#standard',
            $offer['shippingDetails']['hasShippingService']['@id']
        );
        $this->assertSame(
            'https://senisstores.com/refund-policy#policy',
            $offer['hasMerchantReturnPolicy']['@id']
        );
    }

    public function testEmitsOnlyVerifiedIdentifiers(): void
    {
        $product = $this->createProduct([
            'image' => '/a/c/acme.jpg',
            'description' => 'Product',
            'gtin' => '00012345600012',
            'mpn' => 'ACME-925872',
        ]);
        $product->method('getName')->willReturn('ACME product');
        $product->method('getSku')->willReturn('ACME-001');
        $product->method('getFinalPrice')->willReturn(100.0);
        $product->method('getProductUrl')->willReturn('https://senisstores.com/acme.html');
        $product->method('isAvailable')->willReturn(true);
        $product->method('getAttributeText')->willReturnMap([
            ['manufacturer', 'ACME'],
            ['brand', null],
        ]);

        $schema = $this->builder->build($product);

        $this->assertSame('ACME-001', $schema['sku']);
        $this->assertSame('ACME', $schema['brand']['name']);
        $this->assertSame('00012345600012', $schema['gtin14']);
        $this->assertSame('ACME-925872', $schema['mpn']);
    }

    public function testSkipsMerchantMarkupWhenNoRealProductImageExists(): void
    {
        $product = $this->createProduct([
            'image' => 'no_selection',
            'small_image' => 'no_selection',
            'thumbnail' => 'no_selection',
        ]);
        $product->method('getFinalPrice')->willReturn(100.0);

        $this->assertSame([], $this->builder->build($product));
    }

    /**
     * @param array<string, mixed> $data
     * @return Product&MockObject
     */
    private function createProduct(array $data): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getName',
                'getSku',
                'getFinalPrice',
                'getProductUrl',
                'isAvailable',
                'getData',
                'getMediaGalleryImages',
                'getAttributeText',
            ])
            ->getMock();

        $product->method('getData')->willReturnCallback(
            static function ($key = '', $index = null) use ($data) {
                return $key === '' ? $data : ($data[$key] ?? null);
            }
        );
        $product->method('getMediaGalleryImages')->willReturn(null);

        return $product;
    }
}
