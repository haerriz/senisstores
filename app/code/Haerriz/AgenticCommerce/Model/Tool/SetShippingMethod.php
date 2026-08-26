<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class SetShippingMethod implements ToolInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function getName(): string { return 'set_shipping_method'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Select an available shipping method by exact carrier_code and method_code.','parameters'=>['type'=>'object','properties'=>['carrier_code'=>['type'=>'string'],'method_code'=>['type'=>'string']],'required'=>['carrier_code','method_code']]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $state=$this->checkout->setShippingMethod((array)$context['identity'],(string)($arguments['carrier_code']??''),(string)($arguments['method_code']??''),$context['cart_id']??null); return ['checkout'=>$state,'cart'=>$state['cart']??null,'assistant_message'=>(string)__('Shipping method selected.')];
    }
}
