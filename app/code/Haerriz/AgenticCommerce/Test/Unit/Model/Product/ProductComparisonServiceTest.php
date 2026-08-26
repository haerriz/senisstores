<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Product;

use Haerriz\AgenticCommerce\Model\Product\ProductComparisonService;
use Haerriz\AgenticCommerce\Model\Product\ProductExperienceService;
use Haerriz\AgenticCommerce\Model\Config;
use PHPUnit\Framework\TestCase;

class ProductComparisonServiceTest extends TestCase
{
    public function testDescriptionPriceAndInventoryAreComparedFromGroundedExperienceData(): void
    {
        $experience = $this->createMock(ProductExperienceService::class);
        $experience->method('get')->willReturnCallback(static function (string $sku): array {
            if ($sku === 'A') {
                return self::experience('A', 'Course A', 'Pediatric assessment and stabilization course.', '$20.00', 'In stock');
            }
            return self::experience('B', 'Course B', 'Adult CPR and first aid course.', '$30.00', 'Only 2 left');
        });

        $result = (new ProductComparisonService($experience, $this->config()))->compare(
            ['A', 'B'],
            1,
            ['description', 'price', 'inventory']
        );

        self::assertSame(['description', 'price', 'inventory'], $result['focus']);
        $rows = array_column($result['rows'], null, 'key');
        self::assertArrayHasKey('description', $rows);
        self::assertArrayHasKey('price', $rows);
        self::assertArrayHasKey('availability', $rows);
        self::assertContains('Description', $result['differences']);
        self::assertStringContainsString('Course A', $result['assistant_message']);
        self::assertStringContainsString('Course B', $result['assistant_message']);
    }

    public function testComparisonIsBoundedToFourUniqueProducts(): void
    {
        $experience = $this->createMock(ProductExperienceService::class);
        $experience->method('get')->willReturnCallback(
            static fn(string $sku): array => self::experience($sku, 'Product '.$sku, 'Description '.$sku, '$10.00', 'In stock')
        );

        $result = (new ProductComparisonService($experience, $this->config()))->compare(['A','B','C','D','E','A'], 1, ['description']);
        self::assertCount(4, $result['products']);
    }


    public function testGoalAssessmentUsesCatalogEvidenceAndAvoidsSubjectiveWinnerLanguage(): void
    {
        $experience = $this->createMock(ProductExperienceService::class);
        $experience->method('get')->willReturnCallback(static function (string $sku): array {
            return $sku === 'A'
                ? self::experience('A', 'Pediatric Course', 'Pediatric emergency assessment and stabilization training.', '$20.00', 'In stock')
                : self::experience('B', 'Adult Course', 'Adult CPR and first aid training.', '$20.00', 'In stock');
        });

        $result = (new ProductComparisonService($experience, $this->config()))->compare(
            ['A', 'B'], 1, ['description'], null, 'pediatric training'
        );

        self::assertSame('pediatric training', $result['goal']);
        self::assertCount(2, $result['goal_assessment']);
        self::assertGreaterThan($result['goal_assessment'][1]['score'], $result['goal_assessment'][0]['score']);
        self::assertStringContainsString('evidence match', strtolower($result['assistant_message']));
        self::assertStringContainsString('not a subjective quality score', strtolower($result['assistant_message']));
    }

    private function config(): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('getMaxComparisonProducts')->willReturn(4);
        return $config;
    }

    private static function experience(string $sku, string $name, string $description, string $price, string $inventory): array
    {
        return [
            'product' => [
                'sku'=>$sku,
                'name'=>$name,
                'custom_attributes'=>[['code'=>'format','label'=>'Format','value'=>'Digital']],
            ],
            'short_description'=>'',
            'description'=>$description,
            'categories'=>[],
            'inventory'=>['message'=>$inventory,'status'=>'in_stock'],
            'price'=>['formatted_final_price'=>$price,'discount_percent'=>0],
            'options'=>['groups'=>[]],
            'reviews'=>['total_count'=>0,'items'=>[]],
        ];
    }
}
