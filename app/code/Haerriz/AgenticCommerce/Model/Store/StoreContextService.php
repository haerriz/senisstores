<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Store;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Store\Model\StoreManagerInterface;

class StoreContextService
{
    public function __construct(private StoreManagerInterface $stores, private CurrencyFactory $currencyFactory) {}
    public function get(int $storeId): array
    {
        $store=$this->stores->getStore($storeId); $website=$store->getWebsite(); $group=$store->getGroup();
        $currencies=[]; foreach($store->getAvailableCurrencyCodes(true) as $code) $currencies[]=['code'=>$code,'active'=>$code===$store->getCurrentCurrencyCode()];
        $views=[]; foreach($website->getStores() as $view) if($view->getIsActive()) $views[]=['id'=>(int)$view->getId(),'code'=>(string)$view->getCode(),'name'=>(string)$view->getName(),'base_url'=>(string)$view->getBaseUrl()];
        return ['store_id'=>(int)$store->getId(),'store_code'=>(string)$store->getCode(),'store_name'=>(string)$store->getName(),'website'=>(string)$website->getName(),'group'=>(string)$group->getName(),'base_url'=>(string)$store->getBaseUrl(),'currency'=>(string)$store->getCurrentCurrencyCode(),'base_currency'=>(string)$store->getBaseCurrencyCode(),'available_currencies'=>$currencies,'store_views'=>$views];
    }
}
