<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Coupon;

use Haerriz\AgenticCommerce\Model\Cart\CartService;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\Exception\LocalizedException;

class CouponService
{
    public function __construct(private CartService $cartService, private Config $config)
    {
    }

    public function apply(array $identity, string $code, ?string $cartId = null): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        $code = trim($code);
        if ($code === '' || !preg_match('/^[A-Za-z0-9_-]{2,64}$/', $code)) {
            throw new LocalizedException(__('Please provide a valid coupon code.'));
        }
        return $this->cartService->applyCoupon($identity, $code, $cartId);
    }

    public function remove(array $identity, ?string $cartId = null): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        return $this->cartService->removeCoupon($identity, $cartId);
    }

    private function assertEnabled(int $storeId): void
    {
        if (!$this->config->isFeatureEnabled('coupons', $storeId)) {
            throw new LocalizedException(__('Coupon assistant capabilities are disabled.'));
        }
    }
}
