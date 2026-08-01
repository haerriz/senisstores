<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Haerriz\GoogleShoppingFeed\Model\Product\Type\Pool;
use Haerriz\GoogleShoppingFeed\Model\Product\Type\Simple;
use Haerriz\GoogleShoppingFeed\Model\Product\Type\VirtualProduct;
use Haerriz\GoogleShoppingFeed\Model\ProductTypeResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use PHPUnit\Framework\TestCase;

class ProductTypeResolverTest extends TestCase
{
    private function product(string $typeId = 'simple'): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTypeId', 'getStatus'])
            ->getMock();
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        return $product;
    }

    private function profile(): FeedProfile
    {
        return $this->getMockBuilder(FeedProfile::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function createResolver(): ProductTypeResolver
    {
        $pool = new Pool([
            'simple' => new Simple(),
            'virtual' => new VirtualProduct(),
        ]);
        return new ProductTypeResolver($pool);
    }

    public function testSimpleProductProducesOneRow()
    {
        $product = $this->product('simple');
        $this->assertSame([$product], $this->createResolver()->resolve($product, $this->profile()));
    }

    public function testVirtualProductIsExportableWhenEnabled()
    {
        $product = $this->product('virtual');
        $this->assertSame([$product], $this->createResolver()->resolve($product, $this->profile()));
    }

    public function testDisabledProductIsFilteredOut()
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTypeId', 'getStatus'])
            ->getMock();
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getStatus')->willReturn(Status::STATUS_DISABLED);

        $this->assertSame([], $this->createResolver()->resolve($product, $this->profile()));
    }
}
