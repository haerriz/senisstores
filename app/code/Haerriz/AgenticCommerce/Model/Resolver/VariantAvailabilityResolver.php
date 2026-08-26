<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Product\VariantAvailabilityService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class VariantAvailabilityResolver implements ResolverInterface
{
    public function __construct(private VariantAvailabilityService $variants,private CustomerContext $customerContext){}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $identity=$this->customerContext->identityForTool($context,null,'get_variant_availability');
        return $this->variants->resolve(
            (string)($args['sku']??''),(int)$identity['store_id'],
            is_array($args['selections']??null)?$args['selections']:[],(string)($args['query']??''),
            (float)($args['requested_qty']??1),(int)($identity['customer_group_id']??0)
        );
    }
}
