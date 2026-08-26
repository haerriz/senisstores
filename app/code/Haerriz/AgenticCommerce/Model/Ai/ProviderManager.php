<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Observability\TelemetryEmitter;
use Haerriz\AgenticCommerce\Model\Resilience\CircuitBreaker;
use Haerriz\AgenticCommerce\Model\Resilience\ProviderBudgetGuard;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Store-scoped, DI-registry-backed provider router with circuit-breaker fallback.
 * Unknown provider codes are ignored rather than becoming implicit permissions.
 */
class ProviderManager implements ProviderInterface, ResponseProviderInterface
{
    public function __construct(
        private Config $config,
        private ProviderRegistry $registry,
        private CircuitBreaker $circuitBreaker,
        private ProviderBudgetGuard $budgetGuard,
        private StoreManagerInterface $storeManager,
        private TelemetryEmitter $telemetry,
        private LoggerInterface $logger
    ) {}

    public function plan(string $message, array $context, array $toolDefinitions): ?array
    {
        $storeId=(int)($context['identity']['store_id']??$this->storeManager->getStore()->getId());
        foreach($this->sequence($storeId) as $name=>$provider){
            if(!$this->budgetGuard->consume($name,$storeId)){
                $this->telemetry->emit('provider.budget_exhausted',['provider'=>$name,'store_id'=>$storeId]);
                continue;
            }
            if($this->circuitBreaker->isOpen($name,$storeId)){
                $this->telemetry->emit('provider.circuit_open',['provider'=>$name,'store_id'=>$storeId]);
                continue;
            }
            $started=microtime(true);
            try{$plan=$provider->plan($message,$context,$toolDefinitions);}catch(\Throwable $e){$plan=null;$this->logger->warning('Agentic provider threw during planning.',['provider'=>$name,'exception_class'=>$e::class]);}
            $duration=(int)round((microtime(true)-$started)*1000);
            if(is_array($plan)&&!empty($plan['tools'])){
                $this->circuitBreaker->success($name,$storeId);
                $this->telemetry->emit('provider.plan.success',['provider'=>$name,'store_id'=>$storeId,'duration_ms'=>$duration]);
                $plan['provider_used']=$name;return $plan;
            }
            $this->circuitBreaker->failure($name,$storeId);
            $this->telemetry->emit('provider.plan.failure',['provider'=>$name,'store_id'=>$storeId,'duration_ms'=>$duration]);
        }
        return null;
    }

    public function synthesize(string $message, array $facts, array $context = []): ?string
    {
        $storeId=(int)($context['identity']['store_id']??$this->storeManager->getStore()->getId());
        foreach($this->sequence($storeId) as $name=>$provider){
            if(!$this->budgetGuard->consume($name,$storeId)){
                $this->telemetry->emit('provider.budget_exhausted',['provider'=>$name,'store_id'=>$storeId]);
                continue;
            }
            if(!$provider instanceof ResponseProviderInterface||$this->circuitBreaker->isOpen($name,$storeId))continue;
            $started=microtime(true);
            try{$text=$provider->synthesize($message,$facts,$context);}catch(\Throwable $e){$text=null;$this->logger->warning('Agentic provider threw during synthesis.',['provider'=>$name,'exception_class'=>$e::class]);}
            $duration=(int)round((microtime(true)-$started)*1000);
            if(is_string($text)&&trim($text)!==''){
                $this->circuitBreaker->success($name,$storeId);
                $this->telemetry->emit('provider.synthesis.success',['provider'=>$name,'store_id'=>$storeId,'duration_ms'=>$duration]);
                return trim($text);
            }
            $this->circuitBreaker->failure($name,$storeId);
            $this->telemetry->emit('provider.synthesis.failure',['provider'=>$name,'store_id'=>$storeId,'duration_ms'=>$duration]);
        }
        return null;
    }

    /** @return array<string,ProviderInterface> */
    private function sequence(int $storeId): array
    {
        $primary=$this->config->getAiProvider($storeId);if($primary==='deterministic')return[];
        $names=array_values(array_unique(array_merge([$primary],$this->config->getAiFallbackProviders($storeId))));$out=[];
        foreach($names as $name){$provider=$this->registry->get($name);if($provider instanceof ProviderInterface)$out[$name]=$provider;}
        return $out;
    }
}
