<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Ai;

use Haerriz\AgenticCommerce\Model\Ai\ProviderInterface;
use Haerriz\AgenticCommerce\Model\Ai\ProviderManager;
use Haerriz\AgenticCommerce\Model\Ai\ProviderRegistry;
use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Observability\TelemetryEmitter;
use Haerriz\AgenticCommerce\Model\Resilience\CircuitBreaker;
use Haerriz\AgenticCommerce\Model\Resilience\ProviderBudgetGuard;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProviderManagerTest extends TestCase
{
    private function manager(Config $config,array $providers):ProviderManager
    {
        $cb=$this->createMock(CircuitBreaker::class);$cb->method('isOpen')->willReturn(false);
        $budget=$this->createMock(ProviderBudgetGuard::class);$budget->method('consume')->willReturn(true);
        $store=$this->createMock(StoreManagerInterface::class);$store->method('getStore')->willReturn(new class{public function getId():int{return 1;}});
        return new ProviderManager($config,new ProviderRegistry($providers),$cb,$budget,$store,$this->createMock(TelemetryEmitter::class),$this->createMock(LoggerInterface::class));
    }
    public function testFallsBackToSecondConfiguredProvider():void
    {
        $config=$this->createMock(Config::class);$config->method('getAiProvider')->willReturn('openai_responses');$config->method('getAiFallbackProviders')->willReturn(['gemini']);
        $primary=$this->createMock(ProviderInterface::class);$primary->expects(self::once())->method('plan')->willReturn(null);
        $fallback=$this->createMock(ProviderInterface::class);$fallback->expects(self::once())->method('plan')->willReturn(['tools'=>[['name'=>'search_products','arguments'=>[]]]]);
        $plan=$this->manager($config,['openai_responses'=>$primary,'gemini'=>$fallback])->plan('find shoes',[],[]);self::assertSame('gemini',$plan['provider_used']);
    }
    public function testDeterministicSelectionSkipsExternalProviders():void
    {
        $config=$this->createMock(Config::class);$config->method('getAiProvider')->willReturn('deterministic');$config->method('getAiFallbackProviders')->willReturn(['gemini']);$provider=$this->createMock(ProviderInterface::class);$provider->expects(self::never())->method('plan');self::assertNull($this->manager($config,['gemini'=>$provider])->plan('hello',[],[]));
    }
}
