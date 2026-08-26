<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductContentService;
use Magento\Framework\Exception\LocalizedException;

class GetProductContent implements ToolInterface
{
    public function __construct(private ProductContentService $content) {}
    public function getName(): string { return 'get_product_content'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Read shopper-safe product description, short description, highlights and approved storefront specifications for an exact SKU or recent-result position.',
            'parameters'=>['type'=>'object','properties'=>[
                'sku'=>['type'=>'string'],
                'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24],
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
        if($sku==='') throw new LocalizedException(__('Tell me which product you want described.'));
        $data=$this->content->get($sku,(int)($context['identity']['store_id']??0),(int)($context['identity']['customer_group_id']??0));
        return [
            'product_content'=>$data,
            'products'=>[$data['product']],
            'total_count'=>1,
            'assistant_message'=>$data['assistant_message'],
        ];
    }
}
