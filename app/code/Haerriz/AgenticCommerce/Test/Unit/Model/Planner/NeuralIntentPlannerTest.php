<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Planner;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Planner\NeuralIntentPlanner;
use Haerriz\AgenticCommerce\Model\RizAi\FeatureHasher;
use Haerriz\AgenticCommerce\Model\RizAi\NeuralModelRuntime;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class NeuralIntentPlannerTest extends TestCase
{
    public function testHighConfidenceOutOfScopePredictionStopsBroadCatalogFallback(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isRizAiNeuralEnabled')->willReturn(true);
        $config->method('getRizAiNeuralMinConfidence')->willReturn(0.90);
        $config->method('getRizAiNeuralMinMargin')->willReturn(0.18);
        $policy = $this->createMock(ToolPolicy::class);
        $runtime = new NeuralModelRuntime(new FeatureHasher(), new NullLogger());
        $planner = new NeuralIntentPlanner($config, $runtime, $policy);

        $plan = $planner->plan('What is two plus two?', ['identity' => ['store_id' => 1]]);

        self::assertNotNull($plan);
        self::assertSame([], $plan['tools']);
        self::assertSame('out_of_scope', $plan['neural_model']['intent']);
        self::assertNotSame('', trim($plan['assistant_message']));
    }

    public function testSmalltalkCanBeHandledWithoutInventingATool(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isRizAiNeuralEnabled')->willReturn(true);
        $config->method('getRizAiNeuralMinConfidence')->willReturn(0.90);
        $config->method('getRizAiNeuralMinMargin')->willReturn(0.18);
        $planner = new NeuralIntentPlanner(
            $config,
            new NeuralModelRuntime(new FeatureHasher(), new NullLogger()),
            $this->createMock(ToolPolicy::class)
        );

        $plan = $planner->plan('hello there', ['identity' => ['store_id' => 1]]);
        self::assertNotNull($plan);
        self::assertSame([], $plan['tools']);
        self::assertSame('smalltalk', $plan['neural_model']['intent']);
    }
}
