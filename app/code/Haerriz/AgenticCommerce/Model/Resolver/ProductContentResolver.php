<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Product\ProductContentService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class ProductContentResolver implements ResolverInterface
{
    public function __construct(private ProductContentService $content, private CustomerContext $customerContext) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $identity=$this->customerContext->identityForTool($context,null,'get_product_content');
        return $this->content->get((string)($args['sku']??''),(int)$context->getExtensionAttributes()->getStore()->getId(),(int)($identity['customer_group_id']??0));
    }
}
