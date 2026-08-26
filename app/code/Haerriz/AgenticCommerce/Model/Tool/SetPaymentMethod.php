<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class SetPaymentMethod implements ToolInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function getName(): string { return 'set_payment_method'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Select a payment method by exact method code. Never accept card numbers, CVV or secret payment credentials.','parameters'=>['type'=>'object','properties'=>['method_code'=>['type'=>'string']],'required'=>['method_code']]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $state=$this->checkout->setPaymentMethod((array)$context['identity'],(string)($arguments['method_code']??''),$context['cart_id']??null); return ['checkout'=>$state,'cart'=>$state['cart']??null,'assistant_message'=>(string)__('Payment method selected. Sensitive payment details, when required, must be entered in the secure checkout UI.')];
    }
}
