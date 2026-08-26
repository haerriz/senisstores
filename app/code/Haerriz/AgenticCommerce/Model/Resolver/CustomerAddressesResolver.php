<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
class CustomerAddressesResolver implements ResolverInterface { public function __construct(private CustomerAccountService $customers, private CustomerContext $customerContext) {} public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null) { $identity=$this->customerContext->identityForTool($context, null, 'get_customer_addresses'); return $this->customers->addressList($identity); } }
