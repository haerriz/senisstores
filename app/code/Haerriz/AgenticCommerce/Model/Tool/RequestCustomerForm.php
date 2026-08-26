<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Tool;
use Haerriz\AgenticCommerce\Model\Customer\CustomerFormService;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
class RequestCustomerForm implements ToolInterface
{
    public function __construct(private CustomerFormService $forms, private CustomerAccountService $accounts){}
    public function getName():string{return 'request_customer_form';}
    public function getDefinition():array{return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Open a safe structured Magento customer profile or address form. The shopper enters PII directly into Magento; do not ask the model to collect an address.','parameters'=>['type'=>'object','properties'=>['kind'=>['type'=>'string','enum'=>['profile','address']],'address_id'=>['type'=>'integer'],'index'=>['type'=>'integer','minimum'=>0,'maximum'=>100]],'required'=>['kind']]]];}
    public function execute(array $arguments,array $context=[]):array{$identity=(array)$context['identity'];$addressId=isset($arguments['address_id'])?(int)$arguments['address_id']:null;if (!$addressId && array_key_exists('index', $arguments)) {$owned=$this->accounts->addressByPosition($identity,(int)$arguments['index']);$addressId=(int)($owned['id']??0);} $form=$this->forms->build($identity,(string)($arguments['kind']??''),$addressId);return ['form'=>$form,'assistant_message'=>(string)__('Use the secure form below. The entered values are submitted directly to Magento.')];}
}
