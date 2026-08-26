<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/** Shopper-visible Magento price explanation for the current store/customer context. */
class PriceInsightService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private StoreManagerInterface $storeManager,
        private PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function get(string $sku, ?int $storeId = null, ?int $customerGroupId = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new LocalizedException(__('A product SKU is required.'));
        }
        $store = $this->storeManager->getStore($storeId);
        $product = $this->products->get($sku, false, (int)$store->getId(), true);
        if ($customerGroupId !== null) {
            // Product price models inspect this context for tier/group-aware pricing. This is
            // especially important for stateless GraphQL requests where no CustomerSession exists.
            $product->setData('customer_group_id', max(0, $customerGroupId));
        }
        $regular = (float)$product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
        $final = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        $discount = max(0.0, $regular - $final);
        $discountPercent = $regular > 0.00001 ? round(($discount / $regular) * 100, 2) : 0.0;
        $tiers = [];
        foreach (array_slice((array)$product->getTierPrices(), 0, 20) as $tier) {
            $tiers[] = [
                'qty' => (float)$tier->getQty(),
                'value' => (float)$tier->getValue(),
                'formatted_value' => $this->priceCurrency->format((float)$tier->getValue(), false),
                'customer_group_id' => (int)$tier->getCustomerGroupId(),
            ];
        }
        return [
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'regular_price' => $regular,
            'final_price' => $final,
            'formatted_regular_price' => $this->priceCurrency->format($regular, false),
            'formatted_final_price' => $this->priceCurrency->format($final, false),
            'discount_amount' => $discount,
            'formatted_discount_amount' => $this->priceCurrency->format($discount, false),
            'discount_percent' => $discountPercent,
            'special_price' => $product->getSpecialPrice() !== null ? (float)$product->getSpecialPrice() : null,
            'special_from_date' => (string)($product->getSpecialFromDate() ?: ''),
            'special_to_date' => (string)($product->getSpecialToDate() ?: ''),
            'tier_prices' => $tiers,
            'message' => $discount > 0
                ? (string)__('%1 is currently %2, down from %3 (%4% off).', (string)$product->getName(), $this->priceCurrency->format($final, false), $this->priceCurrency->format($regular, false), $discountPercent)
                : (string)__('%1 is currently %2.', (string)$product->getName(), $this->priceCurrency->format($final, false)),
        ];
    }
}
