<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Product\ProductExperienceService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class ProductExperienceResolver implements ResolverInterface
{
    public function __construct(private ProductExperienceService $experience, private CustomerContext $customerContext) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $identity=$this->customerContext->identityForTool($context, null, 'get_product_experience');
        $data=$this->experience->get((string)($args['sku']??''),(int)$context->getExtensionAttributes()->getStore()->getId(),(float)($args['requested_qty']??1),(int)($args['review_limit']??3),(int)($identity['customer_group_id']??0));
        return [
            'product'=>$data['product'], 'short_description'=>$data['short_description'], 'description'=>$data['description'], 'categories'=>$data['categories'],
            'inventory'=>$data['inventory'], 'price'=>$data['price'],
            'options'=>$data['options'], 'reviews'=>$data['reviews'], 'assistant_message'=>$data['assistant_message'],
        ];
    }
}
