<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\PriceInsightService;
use Magento\Framework\Exception\LocalizedException;

class GetProductPrice implements ToolInterface
{
    public function __construct(private PriceInsightService $prices) {}
    public function getName(): string { return 'get_product_price'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Explain current Magento storefront price, regular price, discount and tier prices for an exact SKU or recent product.',
            'parameters'=>['type'=>'object','properties'=>['sku'=>['type'=>'string'],'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24]]],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $sku=trim((string)($arguments['sku']??''));
        if($sku==='' && !empty($arguments['index'])){ $i=max(1,(int)$arguments['index']); $sku=trim((string)($context['recent_products'][$i-1]['sku']??'')); }
        if($sku==='') throw new LocalizedException(__('Tell me which product or SKU you want a price for.'));
        $data=$this->prices->get($sku,(int)($context['identity']['store_id']??0),(int)($context['identity']['customer_group_id']??0));
        return ['price_insight'=>$data,'assistant_message'=>(string)$data['message']];
    }
}
