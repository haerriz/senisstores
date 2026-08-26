<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Customer;

use Haerriz\AgenticCommerce\Model\Store\DirectoryService;
use Magento\Framework\Exception\LocalizedException;

/** Builds structured forms whose submitted PII goes directly to Magento, never through an LLM tool call. */
class CustomerFormService
{
    public function __construct(
        private CustomerAccountService $accounts,
        private DirectoryService $directory
    ) {}

    public function build(array $identity, string $kind, ?int $addressId = null): array
    {
        $kind = strtolower(trim($kind));
        if ($kind === 'profile') {
            $profile = $this->accounts->profile($identity);
            return [
                'id'=>'customer_profile','title'=>(string)__('Edit profile'),'action'=>'update_customer_profile','submit_label'=>(string)__('Save profile'),
                'fields'=>[
                    $this->field('firstname',(string)__('First name'),'text',true,(string)$profile['firstname']),
                    $this->field('lastname',(string)__('Last name'),'text',true,(string)$profile['lastname']),
                ],
            ];
        }
        if ($kind !== 'address') throw new LocalizedException(__('Unsupported customer form.'));
        $address = null;
        if ($addressId && $addressId > 0) $address = $this->accounts->ownedAddress($identity,$addressId);
        $profile = $this->accounts->profile($identity);
        $countries=[];
        foreach($this->directory->countries(300) as $country){$countries[]=['value'=>(string)$country['id'],'label'=>(string)($country['full_name_locale']?:$country['full_name_english']?:$country['id'])];}
        $v = fn(string $key,string $fallback=''): string => (string)($address[$key]??$fallback);
        return [
            'id'=>$addressId?'customer_address_edit':'customer_address_new','title'=>$addressId?(string)__('Edit saved address'):(string)__('Add saved address'),'action'=>'save_customer_address','submit_label'=>(string)__('Save address'),
            'hidden'=>$addressId?[['code'=>'address_id','value'=>(string)$addressId]]:[],
            'fields'=>[
                $this->field('firstname',(string)__('First name'),'text',true,$v('firstname',(string)$profile['firstname'])),
                $this->field('lastname',(string)__('Last name'),'text',true,$v('lastname',(string)$profile['lastname'])),
                $this->field('company',(string)__('Company'),'text',false,$v('company')),
                $this->field('street',(string)__('Street address'),'text',true,implode(', ',(array)($address['street']??[]))),
                $this->field('city',(string)__('City'),'text',true,$v('city')),
                $this->field('country_id',(string)__('Country'),'select',true,$v('country_id'),$countries),
                $this->field('region',(string)__('State / Region'),'text',false,$v('region')),
                $this->field('postcode',(string)__('Postcode'),'text',true,$v('postcode')),
                $this->field('telephone',(string)__('Phone'),'tel',true,$v('telephone')),
                $this->field('default_shipping',(string)__('Default shipping'),'checkbox',false,!empty($address['default_shipping'])?'1':'0'),
                $this->field('default_billing',(string)__('Default billing'),'checkbox',false,!empty($address['default_billing'])?'1':'0'),
            ],
        ];
    }

    private function field(string $name,string $label,string $type,bool $required,string $value='',array $options=[]): array
    {
        return ['name'=>$name,'label'=>$label,'type'=>$type,'required'=>$required,'value'=>$value,'options'=>$options];
    }
}
