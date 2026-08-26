<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Checkout\CheckoutService;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;

class PreparePlaceOrder implements ToolInterface
{
    public function __construct(private CheckoutService $checkout, private ConfirmationService $confirmations) {}
    public function getName(): string { return 'prepare_place_order'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Prepare order placement. This NEVER places the order; it returns a server-bound confirmation request.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $state=$this->checkout->getState((array)$context['identity'],$context['cart_id']??null); if(!$state['ready']) return ['checkout'=>$state,'cart'=>$state['cart']??null,'assistant_message'=>(string)__('Checkout is not ready. Missing: %1.',implode(', ',(array)$state['missing']))]; $snapshot=$this->checkout->confirmationSnapshot((array)$context['identity'],$context['cart_id']??null); $summary=(string)__('Place this order for %1?',$state['cart']['formatted_grand_total']??''); $confirmation=$this->confirmations->create((string)($context['conversation_public_id']??''),(array)$context['identity'],'place_order',$snapshot,$summary); return ['checkout'=>$state,'cart'=>$state['cart']??null,'confirmation'=>$confirmation,'assistant_message'=>$summary.' '.__('Say “confirm order” or use the confirmation button.')];
    }
}
