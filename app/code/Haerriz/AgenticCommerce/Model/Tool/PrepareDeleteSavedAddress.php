<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Magento\Framework\Exception\LocalizedException;

class PrepareDeleteSavedAddress implements ToolInterface
{
    public function __construct(private CustomerAccountService $customers, private ConfirmationService $confirmations) {}
    public function getName(): string { return 'prepare_delete_saved_address'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Prepare deletion of a shopper-owned saved address by ordinal position. Never delete immediately; return a confirmation.',
            'parameters'=>['type'=>'object','properties'=>['index'=>['type'=>'integer','minimum'=>0,'maximum'=>100]],'required'=>['index']],
        ]];
    }
    public function execute(array $arguments,array $context=[]):array
    {
        $index=(int)($arguments['index']??0);
        $owned=$this->customers->addressByPosition((array)$context['identity'],$index);
        $id=(int)($owned['id']??0); if($id<=0)throw new LocalizedException(__('That saved address does not exist.'));
        $summary=(string)__('Delete the saved address in %1, %2?',(string)$owned['city'],(string)$owned['postcode']);
        $confirmation=$this->confirmations->create((string)($context['conversation_public_id']??''),(array)$context['identity'],'delete_customer_address',['address_id'=>$id],$summary);
        return ['confirmation'=>$confirmation,'assistant_message'=>$summary.' '.__('Say “confirm” or use the confirmation button.')];
    }
}
