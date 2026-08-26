<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Haerriz\AgenticCommerce\Model\Store\StoreContextService;
class StoreContextResolver implements ResolverInterface { public function __construct(private StoreContextService $storeContext, private CustomerContext $customerContext) {} public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null) { $this->customerContext->identityForTool($context, null, 'get_store_context'); return $this->storeContext->get((int)$context->getExtensionAttributes()->getStore()->getId()); } }
