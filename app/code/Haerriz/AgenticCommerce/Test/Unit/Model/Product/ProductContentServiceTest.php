<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Product;

use Haerriz\AgenticCommerce\Model\Product\ProductContentService;
use PHPUnit\Framework\TestCase;

class ProductContentServiceTest extends TestCase
{
    public function testPlainTextRemovesMarkupExecutableBlocksAndBoundsContent(): void
    {
        $service = $this->getMockBuilder(ProductContentService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $plain = $service->plainText(
            '<style>.x{display:none}</style><script>alert("x")</script><p>Hello <strong>shopper</strong></p><div>Useful details</div>',
            40
        );

        self::assertStringNotContainsString('alert', $plain);
        self::assertStringNotContainsString('display:none', $plain);
        self::assertStringContainsString('Hello shopper', $plain);
        self::assertLessThanOrEqual(40, mb_strlen($plain));
    }
}
