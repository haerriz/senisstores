<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductQuestionService;
use Magento\Framework\Exception\LocalizedException;

class AnswerProductQuestion implements ToolInterface
{
    public function __construct(private ProductQuestionService $questions) {}
    public function getName(): string { return 'answer_product_question'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Answer a factual question about one product using only its Magento storefront description, highlights and approved display specifications. Missing evidence must be reported as not stated, never guessed.',
            'parameters'=>['type'=>'object','properties'=>[
                'sku'=>['type'=>'string'],
                'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24],
                'question'=>['type'=>'string'],
            ],'required'=>['question']],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $sku=trim((string)($arguments['sku']??''));
        if($sku==='' && !empty($arguments['index'])){
            $i=max(1,(int)$arguments['index']);
            $sku=trim((string)($context['recent_products'][$i-1]['sku']??''));
        }
        if($sku==='') throw new LocalizedException(__('Tell me which product you are asking about.'));
        $data=$this->questions->answer($sku,(string)($arguments['question']??''),(int)($context['identity']['store_id']??0),(int)($context['identity']['customer_group_id']??0));
        return ['product_answer'=>$data,'assistant_message'=>$data['assistant_message']];
    }
}
