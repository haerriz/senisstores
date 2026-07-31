<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Model\CategoryIdResolver;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class CategoryIdResolverTest extends TestCase
{
    public function testNormalizesIdsWithoutOpeningDatabaseConnectionWhenDescendantsAreDisabled()
    {
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->expects($this->never())->method('getConnection');
        $resolver = new CategoryIdResolver($resourceConnection);

        $this->assertSame([3, 5], $resolver->resolve([3, '5', 3, 0], false));
    }
}
