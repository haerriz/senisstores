<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Checkout;

use Haerriz\AgenticCommerce\Api\CheckoutValidationProviderInterface;
use Magento\Quote\Model\Quote;

class CheckoutValidationRegistry
{
    /** @param CheckoutValidationProviderInterface[] $providers */
    public function __construct(private array $providers = []) {}
    public function validate(Quote $quote, array $identity): array
    {
        $out=[];
        foreach($this->providers as $provider){
            if(!$provider instanceof CheckoutValidationProviderInterface) continue;
            foreach($provider->validate($quote,$identity) as $requirement){
                if(!is_array($requirement)||empty($requirement['code']))continue;
                $out[]=['code'=>(string)$requirement['code'],'label'=>(string)($requirement['label']??$requirement['code']),'satisfied'=>(bool)($requirement['satisfied']??false)];
            }
        }
        return $out;
    }
}
