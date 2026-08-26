<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

use Magento\Quote\Model\Quote;

/** Optional modules can inject storefront checkout requirements (agreements, B2B approvals, etc.). */
interface CheckoutValidationProviderInterface
{
    /** @return array<int,array{code:string,label:string,satisfied:bool}> */
    public function validate(Quote $quote, array $identity): array;
}
