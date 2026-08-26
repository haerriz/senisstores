<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ReviewService;
use Magento\Framework\Exception\LocalizedException;

class GetProductReviews implements ToolInterface
{
    public function __construct(private ReviewService $reviews) {}
    public function getName(): string { return 'get_product_reviews'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Read approved reviews for an exact SKU or a server-owned recent product position.',
            'parameters'=>['type'=>'object','properties'=>[
                'sku'=>['type'=>'string'],
                'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24],
                'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>20]
            ]]
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $sku=trim((string)($arguments['sku']??''));
        if($sku==='' && !empty($arguments['index'])){
            $i=max(1,(int)$arguments['index']);
            $sku=trim((string)($context['recent_products'][$i-1]['sku']??''));
        }
        if($sku==='') throw new LocalizedException(__('Tell me which product reviews you want.'));
        $r=$this->reviews->list($sku,(int)$context['identity']['store_id'],(int)($arguments['limit']??5));
        return ['reviews'=>$r,'assistant_message'=>$r['total_count']?(string)__('I found %1 approved review(s).',$r['total_count']):(string)__('There are no approved reviews for this product yet.')];
    }
}
