<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Product\ProductQuestionService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class ProductQuestionResolver implements ResolverInterface
{
    public function __construct(private ProductQuestionService $questions, private CustomerContext $customerContext) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $identity=$this->customerContext->identityForTool($context,null,'answer_product_question');
        return $this->questions->answer((string)($args['sku']??''),(string)($args['question']??''),(int)$context->getExtensionAttributes()->getStore()->getId(),(int)($identity['customer_group_id']??0));
    }
}
