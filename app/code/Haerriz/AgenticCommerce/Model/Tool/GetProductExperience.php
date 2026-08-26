<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductExperienceService;
use Magento\Framework\Exception\LocalizedException;

class GetProductExperience implements ToolInterface
{
    public function __construct(private ProductExperienceService $experience) {}
    public function getName(): string { return 'get_product_experience'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Get a complete shopper-safe product snapshot: product card data, current price, stock/remaining quantity policy, options and approved review summary. Use an exact SKU or recent-result index.',
            'parameters'=>['type'=>'object','properties'=>[
                'sku'=>['type'=>'string'],
                'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24],
                'requested_qty'=>['type'=>'number','minimum'=>0.0001,'maximum'=>10000],
            ]],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $sku=trim((string)($arguments['sku']??''));
        if($sku==='' && !empty($arguments['index'])){
            $i=max(1,(int)$arguments['index']);
            $sku=trim((string)($context['recent_products'][$i-1]['sku']??''));
        }
        if($sku==='') throw new LocalizedException(__('Tell me which product you want details for.'));
        $data=$this->experience->get($sku,(int)($context['identity']['store_id']??0),max(0.0001,(float)($arguments['requested_qty']??1)),3,(int)($context['identity']['customer_group_id']??0));
        return [
            'product_experience'=>$data,
            'products'=>[$data['product']],
            'total_count'=>1,
            'inventory'=>$data['inventory'],
            'price_insight'=>$data['price'],
            'product_options'=>$data['options'],
            'reviews'=>$data['reviews'],
            'assistant_message'=>$data['assistant_message'],
        ];
    }
}
