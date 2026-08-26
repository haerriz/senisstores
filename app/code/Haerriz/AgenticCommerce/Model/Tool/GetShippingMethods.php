<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class GetShippingMethods implements ToolInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function getName(): string { return 'get_shipping_methods'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'List shipping methods currently available for the cart after a shipping address is set.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $items=$this->checkout->getShippingMethods((array)$context['identity'],$context['cart_id']??null); return ['shipping_methods'=>$items,'assistant_message'=>$items?(string)__('I found %1 available shipping method(s).',count($items)):(string)__('No shipping methods are available yet. Set a shipping address first.')];
    }
}
