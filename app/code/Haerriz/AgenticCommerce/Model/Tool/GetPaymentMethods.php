<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class GetPaymentMethods implements ToolInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function getName(): string { return 'get_payment_methods'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'List payment method codes/titles available for the current cart. Never handles card credentials.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $items=$this->checkout->getPaymentMethods((array)$context['identity'],$context['cart_id']??null); return ['payment_methods'=>$items,'assistant_message'=>$items?(string)__('I found %1 available payment method(s).',count($items)):(string)__('No payment methods are currently available.')];
    }
}
