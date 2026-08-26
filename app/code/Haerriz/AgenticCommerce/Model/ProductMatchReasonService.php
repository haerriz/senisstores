<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model;
class ProductMatchReasonService
{
    public function build(array $product, array $arguments): array
    {
        $reasons=[]; $phrase=trim((string)($arguments['phrase']??''));
        if($phrase!=='' && (str_contains(mb_strtolower((string)($product['name']??'')),mb_strtolower($phrase)) || str_contains(mb_strtolower((string)($product['sku']??'')),mb_strtolower($phrase)))) $reasons[]=(string)__('Matches “%1”',$phrase);
        $attrs=[]; foreach((array)($product['custom_attributes']??[]) as $a){ if(is_array($a)) $attrs[(string)($a['code']??'')]=mb_strtolower((string)($a['value']??'')); }
        foreach(array_slice((array)($arguments['filters']??[]),0,4) as $filter){ if(!is_array($filter))continue; $code=(string)($filter['attribute']??''); $values=array_map('strval',(array)($filter['values']??[]));
            if($code==='price'){ $reasons[]=(string)__('Within your price preference'); continue; }
            if(isset($attrs[$code])) foreach($values as $v) if($v!=='' && str_contains($attrs[$code],mb_strtolower($v))){ $reasons[]=(string)__('Matches %1: %2',(string)($filter['label']??$code),$v); break; }
        }
        if(!empty($product['is_salable'])) $reasons[]=(string)__('Currently available');
        return array_slice(array_values(array_unique($reasons)),0,4);
    }
}
