<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Product;

use Haerriz\AgenticCommerce\Model\Product\ProductContentService;
use Haerriz\AgenticCommerce\Model\Product\ProductQuestionService;
use Haerriz\AgenticCommerce\Model\Config;
use PHPUnit\Framework\TestCase;

class ProductQuestionServiceTest extends TestCase
{
    public function testReturnsGroundedEvidenceWhenCatalogContentMatches(): void
    {
        $content = $this->getMockBuilder(ProductContentService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $content->method('get')->willReturn([
            'product' => ['sku' => 'PEARS', 'name' => 'Pediatric Emergency Course'],
            'short_description' => 'Training for pediatric emergency assessment.',
            'description' => 'Designed for pediatric emergency assessment and stabilization. Includes scenario-based learning.',
            'specifications' => [['code'=>'audience','label'=>'Audience','value'=>'Healthcare professionals']],
            'highlights' => ['Scenario-based learning'],
        ]);

        $config = $this->createMock(Config::class);
        $config->method('getMaxQaEvidence')->willReturn(4);
        $result = (new ProductQuestionService($content, $config))->answer('PEARS', 'Does this mention pediatric use?', 1);

        self::assertSame('evidence_found', $result['status']);
        self::assertNotEmpty($result['evidence']);
        self::assertStringContainsString('pediatric', strtolower($result['answer']));
    }

    public function testMissingEvidenceIsNotConvertedIntoAFalseNegativeClaim(): void
    {
        $content = $this->getMockBuilder(ProductContentService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $content->method('get')->willReturn([
            'product' => ['sku' => 'CPR', 'name' => 'CPR Manual'],
            'short_description' => 'A CPR training manual.',
            'description' => 'Covers standard CPR training procedures.',
            'specifications' => [],
            'highlights' => [],
        ]);

        $config = $this->createMock(Config::class);
        $config->method('getMaxQaEvidence')->willReturn(4);
        $result = (new ProductQuestionService($content, $config))->answer('CPR', 'Is this waterproof?', 1);

        self::assertSame('not_stated', $result['status']);
        self::assertSame([], $result['evidence']);
        self::assertStringContainsString('does not necessarily mean', strtolower($result['answer']));
    }
}
