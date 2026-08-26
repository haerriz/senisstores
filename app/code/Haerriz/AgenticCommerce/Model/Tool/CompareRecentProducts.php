<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductComparisonService;

class CompareRecentProducts implements ToolInterface
{
    public function __construct(private ProductComparisonService $comparison) {}

    public function getName(): string { return 'compare_recent_products'; }

    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Compare two to four products from the most recently shown server-side result. Supports description, specifications/attributes, price, inventory, reviews, options and categories.',
            'parameters'=>['type'=>'object','properties'=>[
                'indexes'=>['type'=>'array','minItems'=>2,'maxItems'=>4,'items'=>['type'=>'integer','minimum'=>1,'maximum'=>24]],
                'focus'=>['type'=>'array','items'=>['type'=>'string','enum'=>['description','attributes','price','inventory','reviews','options','categories']]],
                'goal'=>['type'=>'string','maxLength'=>500,'description'=>'Optional shopper use-case or decision goal.'],
            ],'required'=>['indexes']],
        ]];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $recent=is_array($context['recent_products']??null)?$context['recent_products']:[];
        $indexes=array_values(array_unique(array_filter(array_map('intval',(array)($arguments['indexes']??[])),static fn(int $v):bool=>$v>0&&$v<=24)));
        $skus=[];
        foreach(array_slice($indexes,0,4) as $index){
            $sku=trim((string)($recent[$index-1]['sku']??''));
            if($sku!=='')$skus[]=$sku;
        }
        if(count($skus)<2){
            return ['assistant_message'=>(string)__('Tell me at least two recently shown products to compare.')];
        }
        $data=$this->comparison->compare($skus,(int)($context['identity']['store_id']??0),(array)($arguments['focus']??[]),(int)($context['identity']['customer_group_id']??0),(string)($arguments['goal']??''));
        return ['comparison'=>$data,'products'=>$data['products'],'total_count'=>count($data['products']),'assistant_message'=>$data['assistant_message']];
    }
}
