<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\InputSanitizer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class InputSanitizerTest extends TestCase
{
    public function testStripsMarkupAndCollapsesWhitespace(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getMaxMessageLength')->willReturn(100);
        $subject = new InputSanitizer($config);
        self::assertSame('black shoes under 5000', $subject->message('<b>black</b>   shoes under 5000'));
    }

    public function testRejectsEmptyMessage(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getMaxMessageLength')->willReturn(100);
        $subject = new InputSanitizer($config);
        $this->expectException(LocalizedException::class);
        $subject->message('   ');
    }

    public function testDropsClientRecentProductsAndPrivilegeFields(): void
    {
        $config = $this->createMock(Config::class);
        $subject = new InputSanitizer($config);
        $context = $subject->context('{"client_id":"abcdefghijklmnopqrstuvwxyz0123456789ABCDEF","conversation_id":"conv123","recent_products":[{"sku":"FAKE"}],"customer_id":7,"is_admin":true}');
        self::assertArrayHasKey('client_id', $context);
        self::assertArrayHasKey('conversation_id', $context);
        self::assertArrayNotHasKey('recent_products', $context);
        self::assertArrayNotHasKey('customer_id', $context);
        self::assertArrayNotHasKey('is_admin', $context);
    }

    public function testRemovesUntrustedPrivilegeContext(): void
    {
        $config = $this->createMock(Config::class);
        $subject = new InputSanitizer($config);
        $context = $subject->context('{"filters":[],"customer_id":7,"is_admin":true}');
        self::assertArrayHasKey('filters', $context);
        self::assertArrayNotHasKey('customer_id', $context);
        self::assertArrayNotHasKey('is_admin', $context);
    }
}
