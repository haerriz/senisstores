<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resilience;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\App\CacheInterface;

/** Lightweight shared-cache circuit breaker for external AI providers. */
class CircuitBreaker
{
    private const PREFIX='HAERRIZ_AGENTIC_PROVIDER_CB_';
    public function __construct(private CacheInterface $cache,private Config $config){}
    public function isOpen(string $provider,int $storeId=0):bool
    {
        if(!$this->config->isProviderCircuitBreakerEnabled($storeId))return false;
        $state=$this->read($provider,$storeId);
        return (int)($state['open_until']??0)>time();
    }
    public function success(string $provider,int $storeId=0):void{$this->cache->remove($this->key($provider,$storeId));}
    public function failure(string $provider,int $storeId=0):void
    {
        if(!$this->config->isProviderCircuitBreakerEnabled($storeId))return;
        $state=$this->read($provider,$storeId);$failures=(int)($state['failures']??0)+1;
        $threshold=$this->config->getProviderCircuitBreakerThreshold($storeId);
        $openUntil=$failures>=$threshold?time()+$this->config->getProviderCircuitBreakerCooldown($storeId):0;
        $this->cache->save((string)json_encode(['failures'=>$failures,'open_until'=>$openUntil]),$this->key($provider,$storeId),[],max(60,$this->config->getProviderCircuitBreakerCooldown($storeId)*2));
    }
    private function read(string $provider,int $storeId):array{$raw=$this->cache->load($this->key($provider,$storeId));$v=is_string($raw)?json_decode($raw,true):null;return is_array($v)?$v:[];}
    private function key(string $provider,int $storeId):string{return self::PREFIX.$storeId.'_'.sha1($provider);}
}
