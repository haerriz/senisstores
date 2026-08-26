<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductComparisonService;
use Magento\Framework\Exception\LocalizedException;

class CompareProducts implements ToolInterface
{
    public function __construct(private ProductComparisonService $comparison) {}
    public function getName(): string { return 'compare_products'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Compare two to four exact Magento SKUs using storefront description, attributes, price, inventory, reviews, selectable options and categories.',
            'parameters'=>['type'=>'object','properties'=>[
                'skus'=>['type'=>'array','minItems'=>2,'maxItems'=>4,'items'=>['type'=>'string']],
                'focus'=>['type'=>'array','items'=>['type'=>'string','enum'=>['description','attributes','price','inventory','reviews','options','categories']]],
                'goal'=>['type'=>'string','maxLength'=>500,'description'=>'Optional shopper use-case or decision goal. Results are evidence matches, never an ungrounded subjective score.'],
            ],'required'=>['skus']],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $skus=(array)($arguments['skus']??[]);
        if(count($skus)<2) throw new LocalizedException(__('At least two product SKUs are required.'));
        $data=$this->comparison->compare($skus,(int)($context['identity']['store_id']??0),(array)($arguments['focus']??[]),(int)($context['identity']['customer_group_id']??0),(string)($arguments['goal']??''));
        return ['comparison'=>$data,'products'=>$data['products'],'total_count'=>count($data['products']),'assistant_message'=>$data['assistant_message']];
    }
}
