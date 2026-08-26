<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\SuggestionProviderInterface;

/** Aggregates DI-registered suggestion providers and deduplicates against explicit actions. */
class SuggestionService
{
    /** @param SuggestionProviderInterface[] $providers */
    public function __construct(private Config $config, private array $providers = []){}

    public function forResponse(array $response, ?int $storeId = null):array
    {
        $message = mb_strtolower(trim((string)($response['message'] ?? '')));
        if (!empty($response['knowledge']) || !empty($response['store_profile'])
            || str_contains($message, 'only help with questions about this storefront')) {
            return [];
        }
        $suggestions=[];
        foreach($this->providers as $provider){if($provider instanceof SuggestionProviderInterface)$suggestions=array_merge($suggestions,$provider->getSuggestions($response));}
        $blocked=[];foreach((array)($response['actions']??[]) as $action){if(!is_array($action))continue;$label=$this->normalize((string)($action['label']??''));if($label!=='')$blocked[$label]=true;}
        $result=[];$seen=[];foreach($suggestions as $suggestion){$suggestion=trim((string)$suggestion);$key=$this->normalize($suggestion);if($key===''||isset($seen[$key])||isset($blocked[$key]))continue;$seen[$key]=true;$result[]=$suggestion;if(count($result)>=$this->config->getMaxSuggestions($storeId))break;}return$result;
    }
    private function normalize(string $value):string{$value=mb_strtolower(trim($value));return preg_replace('/[^\p{L}\p{N}]+/u',' ',$value)?:$value;}
}
