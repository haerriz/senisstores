<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Haerriz\GoogleShoppingFeed\Model\ProductTypeResolver;
use Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class ProductTypeResolverTest extends TestCase
{
    private function product()
    {
        return $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    private function profile()
    {
        return $this->getMockBuilder(FeedProfile::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    private function createResolver()
    {
        return new ProductTypeResolver(
            $this->createMock(ResourceConnection::class),
            $this->createMock(CollectionFactory::class),
            $this->createMock(Status::class),
            new ProfileConfigReader()
        );
    }

    public function testSimpleProductProducesOneRow()
    {
        $product = $this->product();
        $product->setTypeId('simple');

        $this->assertSame([$product], $this->createResolver()->resolve($product, $this->profile()));
    }

    public function testVirtualAndDownloadableRequireExplicitOptIn()
    {
        $profile = $this->profile();
        $virtual = $this->product();
        $virtual->setTypeId('virtual');
        $downloadable = $this->product();
        $downloadable->setTypeId('downloadable');

        $resolver = $this->createResolver();
        $this->assertSame([], $resolver->resolve($virtual, $profile));
        $this->assertSame([], $resolver->resolve($downloadable, $profile));

        $profile->setData('include_virtual', 1);
        $profile->setData('include_downloadable', 1);
        $this->assertSame([$virtual], $resolver->resolve($virtual, $profile));
        $this->assertSame([$downloadable], $resolver->resolve($downloadable, $profile));
    }
}
