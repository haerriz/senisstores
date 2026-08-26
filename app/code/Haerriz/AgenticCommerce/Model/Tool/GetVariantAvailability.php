<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\VariantAvailabilityService;
use Magento\Framework\Exception\LocalizedException;

class GetVariantAvailability implements ToolInterface
{
    public function __construct(private VariantAvailabilityService $variants) {}
    public function getName(): string { return 'get_variant_availability'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Check storefront-safe stock for a configurable product variant. Use exact Magento option values when known; otherwise pass the shopper query so the server can match visible option labels.',
            'parameters'=>['type'=>'object','properties'=>[
                'sku'=>['type'=>'string'],'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24],
                'query'=>['type'=>'string','maxLength'=>500],'requested_qty'=>['type'=>'number','minimum'=>0.0001,'maximum'=>10000],
                'selections'=>['type'=>'array','items'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'values'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['code','values']]],
            ]],
        ]];
    }
    public function execute(array $arguments, array $context=[]): array
    {
        $sku=trim((string)($arguments['sku']??''));
        if($sku==='' && !empty($arguments['index'])){
            $i=max(1,(int)$arguments['index']);
            $sku=trim((string)($context['recent_products'][$i-1]['sku']??''));
        }
        if($sku==='') throw new LocalizedException(__('Tell me which shown product or exact SKU you want to check.'));
        $data=$this->variants->resolve(
            $sku,
            (int)($context['identity']['store_id']??0),
            is_array($arguments['selections']??null)?$arguments['selections']:[],
            mb_substr(trim((string)($arguments['query']??'')),0,500),
            max(0.0001,(float)($arguments['requested_qty']??1)),
            (int)($context['identity']['customer_group_id']??0)
        );
        return ['variant_availability'=>$data,'assistant_message'=>(string)$data['assistant_message']];
    }
}
