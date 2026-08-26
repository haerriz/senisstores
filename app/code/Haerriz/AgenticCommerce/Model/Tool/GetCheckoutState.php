<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;

class GetCheckoutState implements ToolInterface
{
    public function __construct(private CheckoutService $checkout) {}
    public function getName(): string { return 'get_checkout_state'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Read checkout readiness, addresses, selected shipping/payment and available methods.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $state=$this->checkout->getState((array)$context['identity'],$context['cart_id']??null); return ['checkout'=>$state,'cart'=>$state['cart']??null,'assistant_message'=>$state['ready']?(string)__('Checkout is ready for confirmation.'):(string)__('Checkout still needs: %1.',implode(', ',(array)($state['missing']??[])))];
    }
}
