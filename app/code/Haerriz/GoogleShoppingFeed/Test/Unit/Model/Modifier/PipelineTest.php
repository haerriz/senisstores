<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Modifier;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Pipeline;

class PipelineTest extends TestCase
{
    public function testApplyAppliesModifiers()
    {
        $stripTags = new \Haerriz\GoogleShoppingFeed\Model\Modifier\StripTags();
        $pool = $this->createMock(\Haerriz\GoogleShoppingFeed\Model\Modifier\Pool::class);
        $pool->method('get')->willReturn($stripTags);

        $pipeline = new Pipeline($pool);
        $product = $this->createMock(\Magento\Catalog\Model\Product::class);
        $profile = $this->createMock(\Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface::class);

        $result = $pipeline->apply('<b>Test Title</b>', [['code' => 'striptags']], $product, $profile);
        $this->assertEquals('Test Title', $result);
    }
}
