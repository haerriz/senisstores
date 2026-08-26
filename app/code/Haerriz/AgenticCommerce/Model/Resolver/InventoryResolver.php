<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class InventoryResolver implements ResolverInterface
{
    public function __construct(private InventoryService $inventory, private CustomerContext $customerContext) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $this->customerContext->identityForTool($context, null, 'get_inventory');
        return $this->inventory->get((string)($args['sku']??''),(int)$context->getExtensionAttributes()->getStore()->getId(),(float)($args['requested_qty']??1));
    }
}
