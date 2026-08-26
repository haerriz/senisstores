<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;

class GetCustomerProfile implements ToolInterface
{
    public function __construct(private CustomerAccountService $customers) {}
    public function getName(): string { return 'get_customer_profile'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Read the signed-in shopper profile.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $profile=$this->customers->profile((array)$context['identity']); return ['customer'=>$profile,'assistant_message'=>(string)__('You are signed in as %1 %2.',$profile['firstname'],$profile['lastname'])];
    }
}
