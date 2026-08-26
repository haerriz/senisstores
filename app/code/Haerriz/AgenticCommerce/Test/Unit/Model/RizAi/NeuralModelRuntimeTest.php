<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\RizAi;

use Haerriz\AgenticCommerce\Model\RizAi\FeatureHasher;
use Haerriz\AgenticCommerce\Model\RizAi\NeuralModelRuntime;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class NeuralModelRuntimeTest extends TestCase
{
    public function testBundledWeightsAreLoadableAndClassifyCommerceIntent(): void
    {
        $runtime = new NeuralModelRuntime(new FeatureHasher(), new NullLogger());
        $prediction = $runtime->predict('Where is your store located?');

        self::assertTrue($prediction['available']);
        self::assertSame('rizai-commerce-intent-v1', $prediction['model_id']);
        self::assertSame('feed_forward_neural_network', $prediction['model_type']);
        self::assertSame('get_store_information', $prediction['intent']);
        self::assertGreaterThan(0.90, $prediction['confidence']);
        self::assertGreaterThan(0.18, $prediction['margin']);
        self::assertTrue($runtime->metadata()['checksum_verified']);
    }

    public function testOutOfScopeLanguageIsRepresentedByTheModel(): void
    {
        $runtime = new NeuralModelRuntime(new FeatureHasher(), new NullLogger());
        $prediction = $runtime->predict('What is two plus two?');

        self::assertTrue($prediction['available']);
        self::assertSame('out_of_scope', $prediction['intent']);
        self::assertGreaterThan(0.90, $prediction['confidence']);
    }

    public function testPriceAndComparisonAreLearnedIntents(): void
    {
        $runtime = new NeuralModelRuntime(new FeatureHasher(), new NullLogger());
        self::assertSame('price', $runtime->predict('How much does this cost?')['intent']);
        self::assertSame('compare_products', $runtime->predict('Compare these two items')['intent']);
    }
}
