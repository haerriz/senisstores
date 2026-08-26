<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ReviewService;

class SubmitProductReview implements ToolInterface
{
    public function __construct(private ReviewService $reviews) {}
    public function getName(): string { return 'submit_product_review'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Submit a product review for moderation only when the shopper explicitly provides SKU, title and review text.','parameters'=>['type'=>'object','properties'=>['sku'=>['type'=>'string'],'title'=>['type'=>'string'],'detail'=>['type'=>'string'],'nickname'=>['type'=>'string']],'required'=>['sku','title','detail']]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        return $this->reviews->submit((array)$context['identity'],(string)($arguments['sku']??''),(string)($arguments['title']??''),(string)($arguments['detail']??''),(string)($arguments['nickname']??''));
    }
}
