<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Modifier;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Pool;
use Haerriz\GoogleShoppingFeed\Model\Modifier\ModifierInterface;
use Magento\Catalog\Model\Product;

class PoolTest extends TestCase
{
    public function testApplyWithNoModifierCode()
    {
        $pool = new Pool([]);
        $productMock = $this->createMock(Product::class);
        $this->assertEquals('value', $pool->apply('value', '', $productMock));
    }

    public function testApplyWithArgument()
    {
        $modifierMock = $this->createMock(ModifierInterface::class);
        $productMock = $this->createMock(Product::class);

        $modifierMock->expects($this->once())
            ->method('modify')
            ->with('value', $productMock, 'argVal')
            ->willReturn('modified');

        $pool = new Pool(['test_mod' => $modifierMock]);
        $this->assertEquals('modified', $pool->apply('value', 'test_mod(argVal)', $productMock));
    }

    public function testApplyWithoutArgument()
    {
        $modifierMock = $this->createMock(ModifierInterface::class);
        $productMock = $this->createMock(Product::class);

        $modifierMock->expects($this->once())
            ->method('modify')
            ->with('value', $productMock, null)
            ->willReturn('modified');

        $pool = new Pool(['test_mod' => $modifierMock]);
        $this->assertEquals('modified', $pool->apply('value', 'test_mod', $productMock));
    }
}
