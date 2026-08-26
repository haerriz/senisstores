<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Learning;

use Haerriz\AgenticCommerce\Model\Learning\PhraseNormalizer;
use PHPUnit\Framework\TestCase;

class PhraseNormalizerTest extends TestCase
{
    public function testRedactsEmailPhoneUrlOrderAndNumbers(): void
    {
        $value = (new PhraseNormalizer())->normalize('Email me at person@example.com or +91 98765 43210 about order #123456 at https://example.com — qty 25');
        self::assertStringContainsString('<email>', $value);
        self::assertStringContainsString('<phone>', $value);
        self::assertStringContainsString('<url>', $value);
        self::assertStringContainsString('<order>', $value);
        self::assertStringContainsString('<n>', $value);
        self::assertStringNotContainsString('example.com', $value);
        self::assertStringNotContainsString('98765', $value);
    }
}
