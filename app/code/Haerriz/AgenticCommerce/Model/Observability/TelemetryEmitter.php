<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Observability;

use Haerriz\AgenticCommerce\Api\TelemetryProcessorInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;

/** Extension-neutral telemetry hook for New Relic/Datadog/OpenTelemetry adapters. */
class TelemetryEmitter
{
    /** @param TelemetryProcessorInterface[] $processors */
    public function __construct(private ManagerInterface $events,private LoggerInterface $logger, private array $processors = []){}
    public function traceId():string{return bin2hex(random_bytes(16));}
    public function emit(string $event,array $attributes=[]):void
    {
        $safe=$this->sanitize($attributes);$this->events->dispatch('haerriz_agentic_telemetry',['event_name'=>$event,'attributes'=>$safe]);
        foreach($this->processors as $processor){
            if(!$processor instanceof TelemetryProcessorInterface)continue;
            try{$processor->emit($event,$safe);}catch(\Throwable $e){$this->logger->warning('Agentic telemetry processor failed.',['processor'=>$processor::class,'exception_class'=>$e::class]);}
        }
        if(($safe['level']??'')==='debug')$this->logger->debug('Agentic telemetry: '.$event,$safe);
    }
    private function sanitize(array $values):array
    {
        $out=[];foreach(array_slice($values,0,30,true) as $k=>$v){$key=mb_substr((string)$k,0,64);if(preg_match('/password|secret|token|authorization|cookie|email|phone|address|card|cvv|cvc|pan/i',$key))continue;if(is_scalar($v)||$v===null)$out[$key]=is_string($v)?mb_substr($v,0,300):$v;}return $out;
    }
}
