<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class UseSavedShippingAddress implements ToolInterface
{
    public function __construct(private CustomerAccountService $customers, private CheckoutService $checkout) {}
    public function getName(): string { return 'use_saved_shipping_address'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Use a signed-in customer saved address as the shipping address. Address positions are one-based.','parameters'=>['type'=>'object','properties'=>['index'=>['type'=>'integer','minimum'=>1]],'required'=>['index']]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $address=$this->customers->addressByPosition((array)$context['identity'],max(1,(int)($arguments['index']??1))); $state=$this->checkout->useCustomerAddress((array)$context['identity'],'shipping',$address,$context['cart_id']??null); return ['checkout'=>$state,'cart'=>$state['cart']??null,'assistant_message'=>(string)__('Saved shipping address selected.')];
    }
}
