<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
class CheckoutStateResolver implements ResolverInterface { public function __construct(private CheckoutService $checkout, private CustomerContext $customerContext) {} public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null) { $identity=$this->customerContext->identityForTool($context, $args['client_id']??null, 'get_checkout_state'); return $this->checkout->getState($identity,$args['cart_id']??null); } }
