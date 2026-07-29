<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Modifier;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Modifier\PrependText;
use Haerriz\GoogleShoppingFeed\Model\Modifier\AppendText;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Capitalize;
use Haerriz\GoogleShoppingFeed\Model\Modifier\GoogleTaxonomy;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;

class ModifierTest extends TestCase
{
    protected $productMock;

    protected function setUp(): void
    {
        $this->productMock = $this->createMock(Product::class);
    }

    public function testPrependText()
    {
        $modifier = new PrependText();
        $this->assertEquals('PrefixValue', $modifier->modify('Value', $this->productMock, 'Prefix'));
        $this->assertEquals('Value', $modifier->modify('Value', $this->productMock, null));
    }

    public function testAppendText()
    {
        $modifier = new AppendText();
        $this->assertEquals('ValueSuffix', $modifier->modify('Value', $this->productMock, 'Suffix'));
        $this->assertEquals('Value', $modifier->modify('Value', $this->productMock, null));
    }

    public function testCapitalize()
    {
        $modifier = new Capitalize();
        $this->assertEquals('VALUE', $modifier->modify('Value', $this->productMock));
    }

    public function testGoogleTaxonomy()
    {
        $categoryRepositoryMock = $this->createMock(CategoryRepositoryInterface::class);
        $categoryMock = $this->createMock(Category::class);

        $this->productMock->expects($this->once())
            ->method('getCategoryIds')
            ->willReturn([10]);
        $this->productMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn(1);

        $categoryRepositoryMock->expects($this->once())
            ->method('get')
            ->with(10, 1)
            ->willReturn($categoryMock);

        $categoryMock->expects($this->once())
            ->method('getData')
            ->with('google_product_category')
            ->willReturn('Apparel & Accessories');

        $modifier = new GoogleTaxonomy($categoryRepositoryMock);
        $this->assertEquals('Apparel & Accessories', $modifier->modify('10', $this->productMock));
    }
}
