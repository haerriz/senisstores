<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Storage\AdapterPool;
use Haerriz\GoogleShoppingFeed\Model\Storage\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;

class AdapterPoolTest extends TestCase
{
    public function testGetAdapterSuccess()
    {
        $adapterMock = $this->createMock(AdapterInterface::class);
        $pool = new AdapterPool(['local' => $adapterMock]);

        $this->assertSame($adapterMock, $pool->get('local'));
    }

    public function testGetAdapterThrowsException()
    {
        $pool = new AdapterPool([]);
        $this->expectException(LocalizedException::class);
        $pool->get('invalid');
    }
}
