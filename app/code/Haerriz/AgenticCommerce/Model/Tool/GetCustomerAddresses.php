<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;

class GetCustomerAddresses implements ToolInterface
{
    public function __construct(private CustomerAccountService $customers) {}
    public function getName(): string { return 'get_customer_addresses'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'List saved customer addresses by safe position.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $items=$this->customers->addressList((array)$context['identity']); return ['addresses'=>$items,'assistant_message'=>$items?(string)__('You have %1 saved address(es).',count($items)):(string)__('You do not have any saved addresses.')];
    }
}
