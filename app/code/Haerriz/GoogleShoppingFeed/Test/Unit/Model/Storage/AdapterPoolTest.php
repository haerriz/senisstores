<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Storage\AdapterPool;
use Haerriz\GoogleShoppingFeed\Model\Delivery\DeliveryPool;

class AdapterPoolTest extends TestCase
{
    public function testGetResolvesAdapter()
    {
        $deliveryPool = $this->createMock(DeliveryPool::class);
        $adapter = $this->createMock(\Haerriz\GoogleShoppingFeed\Api\DeliveryAdapterInterface::class);
        $deliveryPool->method('get')->with('local')->willReturn($adapter);

        $pool = new AdapterPool($deliveryPool);
        $resolved = $pool->get('local');
        $this->assertSame($adapter, $resolved);
    }
}
