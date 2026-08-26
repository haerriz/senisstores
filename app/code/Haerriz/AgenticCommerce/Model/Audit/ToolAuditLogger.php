<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Audit;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ToolAuditLogger
{
    public function __construct(
        private ResourceConnection $resource,
        private Config $config,
        private ToolPolicy $policy,
        private StoreManagerInterface $stores,
        private LoggerInterface $logger
    ) {}

    public function log(int $conversationId, array $identity, string $tool, array $arguments, string $outcome, int $durationMs, ?string $errorClass = null, ?string $message = null): void
    {
        $storeId=(int)($identity['store_id']??0);
        if (!$this->config->isFeatureEnabled('audit', $storeId)) return;
        try {
            $client=(string)($identity['client_id']??'');
            $this->resource->getConnection()->insert($this->resource->getTableName('haerriz_agentic_tool_audit'), [
                'conversation_id'=>$conversationId ?: null,
                'store_id'=>$storeId,
                'customer_id'=>(int)($identity['customer_id']??0) ?: null,
                'client_hash'=>$client !== '' ? hash('sha256',$client) : null,
                'tool_name'=>mb_substr($tool,0,64),
                'message_hash'=>$message !== null && trim($message) !== '' ? hash('sha256', trim($message)) : null,
                'risk_level'=>(string)$this->policy->metadata($tool,$storeId)['risk_level'],
                'outcome'=>mb_substr($outcome,0,24),
                'duration_ms'=>max(0,$durationMs),
                'arguments_json'=>json_encode($this->sanitizeArguments($arguments), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                'error_class'=>$errorClass ? mb_substr($errorClass,0,255) : null,
            ]);
        } catch (\Throwable $e) { $this->logger->debug('Agentic Commerce audit logging failed.', ['exception'=>$e]); }
    }

    public function cleanup(): void
    {
        try {
            $connection=$this->resource->getConnection(); $table=$this->resource->getTableName('haerriz_agentic_tool_audit');
            foreach ($this->stores->getStores(false) as $store) {
                $storeId=(int)$store->getId(); $days=$this->config->getAuditRetentionDays($storeId);
                $cutoff=(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-'.$days.' days')->format('Y-m-d H:i:s');
                $connection->delete($table, ['store_id = ?'=>$storeId, 'created_at < ?'=>$cutoff]);
            }
        } catch (\Throwable $e) { $this->logger->warning('Agentic Commerce audit cleanup failed.', ['exception'=>$e]); }
    }

    private function sanitizeArguments(array $arguments): array
    {
        $blocked=['cart_id','client_id','session_id','customer_id','token','authorization','email','telephone','phone','firstname','lastname','company','street','city','region','region_code','region_id','postcode','country_id','password','card_number','cc_number','cvv','cvc']; $result=[];
        foreach($arguments as $key=>$value){
            if(in_array(mb_strtolower((string)$key),$blocked,true)){ $result[$key]='[redacted]'; continue; }
            if(is_array($value)) $result[$key]=$this->sanitizeArguments($value);
            elseif(is_scalar($value)||$value===null) $result[$key]=is_string($value)?mb_substr($value,0,500):$value;
        }
        return $result;
    }
}
