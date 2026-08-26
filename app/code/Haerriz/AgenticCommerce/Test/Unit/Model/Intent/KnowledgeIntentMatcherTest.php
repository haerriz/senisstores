<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Intent;

use Haerriz\AgenticCommerce\Model\Intent\KnowledgeIntentMatcher;
use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;
use PHPUnit\Framework\TestCase;

class KnowledgeIntentMatcherTest extends TestCase
{
    public function testMatchesStorefrontKnowledgeQuestions(): void
    {
        $matcher = new KnowledgeIntentMatcher($this->createMock(KnowledgeService::class));
        self::assertTrue($matcher->matches('can i donate'));
        self::assertTrue($matcher->matches('what is your return policy?'));
        self::assertTrue($matcher->matches('tell me about blended learning'));
    }

    public function testDoesNotMisclassifyActionsOrArithmetic(): void
    {
        $matcher = new KnowledgeIntentMatcher($this->createMock(KnowledgeService::class));
        self::assertFalse($matcher->matches('open the returns page'));
        self::assertFalse($matcher->matches('2 + 2'));
        self::assertFalse($matcher->matches('what is in my cart?'));
    }

    public function testUsesCmsDataForTopicsNotListedInCoreGrammar(): void
    {
        $knowledge = $this->createMock(KnowledgeService::class);
        $knowledge->method('hasRelevantContent')->willReturnMap([
            ['what about tax exemption', true],
            ['link of tax exemption', true],
        ]);
        $knowledge->method('hasExactContent')->willReturnMap([
            ['tax exemption', true],
            ['unrelated product', false],
        ]);
        $matcher = new KnowledgeIntentMatcher($knowledge);

        self::assertTrue($matcher->matches('what about tax exemption'));
        self::assertTrue($matcher->matches('link of tax exemption'));
        self::assertTrue($matcher->matches('tax exemption'));
        self::assertFalse($matcher->matches('unrelated product'));
    }

    public function testNavigationUsesExactActiveCmsTarget(): void
    {
        $knowledge = $this->createMock(KnowledgeService::class);
        $knowledge->method('hasExactContent')->willReturnMap([
            ['tax exemption', true],
            ['unknown destination', false],
        ]);
        $matcher = new KnowledgeIntentMatcher($knowledge);

        self::assertSame('tax exemption', $matcher->navigationTarget('open the tax exemption page'));
        self::assertNull($matcher->navigationTarget('open unknown destination'));
    }
}
