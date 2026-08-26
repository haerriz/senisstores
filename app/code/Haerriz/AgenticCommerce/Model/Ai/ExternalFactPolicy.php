<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Prompt\PromptRedactor;

/** Whitelists facts that may be sent to a merchant-configured external model for wording only. */
class ExternalFactPolicy
{
    public function __construct(private Config $config, private PromptRedactor $redactor) {}

    public function facts(array $response, ?int $storeId=null): array
    {
        $scope=$this->config->getAiExternalDataScope($storeId);
        if ($scope==='disabled') return [];
        $keys=['products','total_count','facets','inventory','inventories','price_insight','product_content','product_answer','comparison','variant_availability','product_experience','reviews','knowledge','store_context','store_profile'];
        if ($scope==='commerce_without_pii') $keys=array_merge($keys,['cart','wishlist','shipping_methods','payment_methods','checkout']);
        $facts=[]; foreach ($keys as $key) {
            if (!isset($response[$key]) || $response[$key]===[] || $response[$key]===null) continue;
            // A default zero result count alone is not evidence and must not invite an external
            // model to rewrite an otherwise authoritative navigation/tool response.
            if ($key==='total_count' && (int)$response[$key]===0) continue;
            $facts[$key]=$this->stripSensitive($response[$key]);
        }
        return (array)$this->redactor->redact($facts);
    }

    private function stripSensitive(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        $blocked=['customer_id','client_id','cart_id','quote_id','item_id','address_id','email','telephone','street','postcode','firstname','lastname','token','confirmation_token','password','payment_data'];
        $out=[];
        foreach ($value as $k=>$v) {
            if (is_string($k) && in_array(mb_strtolower($k),$blocked,true)) continue;
            $out[$k]=$this->stripSensitive($v);
        }
        return $out;
    }
}
