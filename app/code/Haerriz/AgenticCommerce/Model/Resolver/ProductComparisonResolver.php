<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Product\ProductComparisonService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class ProductComparisonResolver implements ResolverInterface
{
    public function __construct(private ProductComparisonService $comparison, private CustomerContext $customerContext) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $identity=$this->customerContext->identityForTool($context,null,'compare_products');
        return $this->comparison->compare((array)($args['skus']??[]),(int)$context->getExtensionAttributes()->getStore()->getId(),(array)($args['focus']??[]),(int)($identity['customer_group_id']??0),mb_substr(trim((string)($args['goal']??'')),0,500));
    }
}
