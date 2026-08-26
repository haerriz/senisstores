<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Haerriz\AgenticCommerce\Model\Product\ProductOptionService;
use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;

class CartService
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartManagementInterface $cartManagement,
        private CheckoutSession $checkoutSession,
        private QuoteIdMaskFactory $quoteIdMaskFactory,
        private ProductRepositoryInterface $productRepository,
        private PriceCurrencyInterface $priceCurrency,
        private ProductOptionService $productOptions,
        private InventoryService $inventory
    ) {
    }

    public function getSummary(array $identity, ?string $cartId = null): array
    {
        $quote = $this->resolveQuote($identity, $cartId, false);
        if (!$quote || !(int)$quote->getId()) {
            return $this->emptySummary($cartId);
        }
        return $this->presentQuote($quote, $cartId);
    }

    public function addProduct(array $identity, string $sku, float $qty = 1.0, ?string $cartId = null, array $selections = []): array
    {
        $qty = max(1.0, min(100.0, $qty));
        $storeId = (int)$identity['store_id'];
        $product = $this->productRepository->get($sku, false, $storeId, true);
        if (!$product->isSalable()) {
            throw new LocalizedException(__('This product is currently unavailable.'));
        }

        $optionSchema = $this->productOptions->describe($sku, $storeId);
        if (!empty($optionSchema['requires_options'])) {
            $normalized = $this->productOptions->normalizeSelections($optionSchema, $selections);
            if (!empty($normalized['missing'])
                || ($selections === [] && !empty($optionSchema['requires_options']))) {
                $message = empty($optionSchema['chat_supported'])
                    ? (string)__('This product has an option that must be configured securely on the product page.')
                    : (string)__('Please choose the required options for %1 before adding it to the cart.', $product->getName());
                return [
                    'added' => false, 'requires_options' => true, 'product_options' => $optionSchema,
                    'assistant_message' => $message,
                    'actions' => [[
                        'type' => 'product_options', 'label' => (string)__('Choose options for %1', $product->getName()), 'url' => (string)$product->getProductUrl(),
                    ]],
                    'cart' => $this->getSummary($identity, $cartId),
                ];
            }
            $selections = $normalized['selections'];
        } elseif ($selections !== []) {
            $selections = $this->productOptions->normalizeSelections($optionSchema, $selections)['selections'];
        }

        // Give deterministic inventory errors for simple storefront products before mutating the quote.
        // Composite products remain authoritative through Magento's type-specific addProduct validation.
        if (in_array((string)$product->getTypeId(), ['simple', 'virtual', 'downloadable'], true)) {
            try {
                $availability = $this->inventory->getForProduct($product, $storeId, $qty);
                if (empty($availability['requested_qty_salable'])) {
                    $min = (float)($availability['min_sale_qty'] ?? 0);
                    $max = (float)($availability['max_sale_qty'] ?? 0);
                    $inc = (float)($availability['qty_increments'] ?? 0);
                    if (empty($availability['meets_min_sale_qty']) && $min > 0) {
                        throw new LocalizedException(__('The minimum purchase quantity for %1 is %2.', $product->getName(), $min));
                    }
                    if (empty($availability['meets_max_sale_qty']) && $max > 0) {
                        throw new LocalizedException(__('The maximum purchase quantity for %1 is %2.', $product->getName(), $max));
                    }
                    if (empty($availability['meets_qty_increment']) && $inc > 0) {
                        throw new LocalizedException(__('%1 must be purchased in increments of %2.', $product->getName(), $inc));
                    }
                    throw new LocalizedException(__('The requested quantity of %1 is not currently available.', $product->getName()));
                }
            } catch (LocalizedException $e) {
                throw $e;
            } catch (\Throwable) {
                // Inventory extensions may be non-standard. Magento's quote layer remains the final authority.
            }
        }

        $quote = $this->resolveQuote($identity, $cartId, true);
        $buyRequest = $this->productOptions->buildBuyRequest($product, $qty, $selections);
        $result = $quote->addProduct($product, $buyRequest);
        if (is_string($result)) {
            throw new LocalizedException(__($result));
        }
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->syncStorefrontSession($identity, $quote);
        $summary = $this->presentQuote($quote, $cartId);
        return [
            'added' => true,
            'requires_options' => false,
            'assistant_message' => (string)__('%1 was added to your cart. Your cart now has %2 item(s).', $product->getName(), $summary['items_count']),
            'cart' => $summary,
        ];
    }

    public function removeItem(array $identity, ?int $itemId = null, ?string $sku = null, ?string $cartId = null): array
    {
        $quote = $this->resolveQuote($identity, $cartId, false);
        if (!$quote || !(int)$quote->getId()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }
        $target = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($itemId && (int)$item->getItemId() === $itemId) {
                $target = $item;
                break;
            }
            if ($sku && hash_equals((string)$item->getSku(), $sku)) {
                $target = $item;
                break;
            }
        }
        if (!$target) {
            throw new LocalizedException(__('I could not find that item in your cart.'));
        }
        $name = (string)$target->getName();
        $quote->removeItem((int)$target->getItemId());
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->syncStorefrontSession($identity, $quote);
        $summary = $this->presentQuote($quote, $cartId);
        return [
            'removed' => true,
            'assistant_message' => (string)__('%1 was removed from your cart.', $name),
            'cart' => $summary,
        ];
    }

    public function updateItem(array $identity, int $itemId, float $qty, ?string $cartId = null): array
    {
        if ($itemId <= 0) {
            throw new LocalizedException(__('A valid cart item id is required.'));
        }
        $qty = max(0.0, min(100.0, $qty));
        if ($qty <= 0.0) {
            return $this->removeItem($identity, $itemId, null, $cartId);
        }
        $quote = $this->resolveQuote($identity, $cartId, false);
        if (!$quote || !(int)$quote->getId()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }
        $target = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int)$item->getItemId() === $itemId) {
                $target = $item;
                break;
            }
        }
        if (!$target) {
            throw new LocalizedException(__('I could not find that item in your cart.'));
        }
        $name = (string)$target->getName();
        $result = $quote->updateItem($itemId, new DataObject(['qty' => $qty]));
        if (is_string($result)) {
            throw new LocalizedException(__($result));
        }
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->syncStorefrontSession($identity, $quote);
        $summary = $this->presentQuote($quote, $cartId);
        return [
            'updated' => true,
            'assistant_message' => (string)__('Updated %1 to quantity %2.', $name, $qty),
            'cart' => $summary,
        ];
    }

    public function clear(array $identity, ?string $cartId = null): array
    {
        $quote = $this->resolveQuote($identity, $cartId, false);
        if (!$quote || !(int)$quote->getId()) {
            return [
                'cleared' => true,
                'assistant_message' => (string)__('Your cart is already empty.'),
                'cart' => $this->emptySummary($cartId),
            ];
        }
        foreach ($quote->getAllVisibleItems() as $item) {
            $quote->removeItem((int)$item->getItemId());
        }
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->syncStorefrontSession($identity, $quote);
        return [
            'cleared' => true,
            'assistant_message' => (string)__('Your cart is now empty.'),
            'cart' => $this->presentQuote($quote, $cartId),
        ];
    }


    public function applyCoupon(array $identity, string $code, ?string $cartId = null): array
    {
        $quote = $this->resolveQuote($identity, $cartId, false);
        if (!$quote || !(int)$quote->getId() || !$quote->getItemsCount()) {
            throw new LocalizedException(__('Add an item to your cart before applying a coupon.'));
        }
        $quote->setCouponCode($code);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->syncStorefrontSession($identity, $quote);
        if (strcasecmp((string)$quote->getCouponCode(), $code) !== 0) {
            throw new LocalizedException(__('The coupon code could not be applied.'));
        }
        return [
            'coupon_applied' => true,
            'assistant_message' => (string)__('Coupon %1 was applied to your cart.', $code),
            'cart' => $this->presentQuote($quote, $cartId),
        ];
    }

    public function removeCoupon(array $identity, ?string $cartId = null): array
    {
        $quote = $this->resolveQuote($identity, $cartId, false);
        if (!$quote || !(int)$quote->getId()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }
        if (!(string)$quote->getCouponCode()) {
            return [
                'coupon_removed' => true,
                'assistant_message' => (string)__('There is no coupon on your cart.'),
                'cart' => $this->presentQuote($quote, $cartId),
            ];
        }
        $quote->setCouponCode('');
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->syncStorefrontSession($identity, $quote);
        return [
            'coupon_removed' => true,
            'assistant_message' => (string)__('The coupon was removed from your cart.'),
            'cart' => $this->presentQuote($quote, $cartId),
        ];
    }

    public function resolveQuote(array $identity, ?string $cartId, bool $create): ?Quote
    {
        $customerId = (int)($identity['customer_id'] ?? 0);
        if ($customerId > 0) {
            try {
                $quote = $this->cartRepository->getActiveForCustomer($customerId);
            } catch (\Throwable $e) {
                if (!$create) {
                    return null;
                }
                $this->cartManagement->createEmptyCartForCustomer($customerId);
                $quote = $this->cartRepository->getActiveForCustomer($customerId);
            }
            $this->assertStore($quote, (int)$identity['store_id']);
            return $quote;
        }

        if (($identity['channel'] ?? '') === 'storefront' && ($cartId === null || $cartId === '')) {
            $quote = $this->checkoutSession->getQuote();
            if ($create && !(int)$quote->getId()) {
                $quote->setStoreId((int)$identity['store_id']);
            }
            $this->assertGuestQuote($quote, (int)$identity['store_id']);
            return $quote;
        }

        $maskedId = trim((string)$cartId);
        if ($maskedId === '') {
            if ($create) {
                throw new LocalizedException(__('A masked guest cart ID is required for headless guest cart operations. Create a guest cart with Magento GraphQL first.'));
            }
            return null;
        }
        if (ctype_digit($maskedId)) {
            throw new AuthorizationException(__('Numeric quote IDs are never accepted for guest cart operations.'));
        }
        $mask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');
        $quoteId = (int)$mask->getQuoteId();
        if ($quoteId <= 0) {
            throw new LocalizedException(__('The guest cart ID is invalid or expired.'));
        }
        $quote = $this->cartRepository->getActive($quoteId);
        $this->assertGuestQuote($quote, (int)$identity['store_id']);
        return $quote;
    }

    private function assertGuestQuote(Quote $quote, int $storeId): void
    {
        if ((int)$quote->getCustomerId() > 0) {
            throw new AuthorizationException(__('A customer-owned cart cannot be accessed as a guest.'));
        }
        $this->assertStore($quote, $storeId);
    }

    private function assertStore(Quote $quote, int $storeId): void
    {
        if ((int)$quote->getStoreId() !== $storeId) {
            throw new AuthorizationException(__('The cart belongs to a different store view.'));
        }
    }

    /**
     * Keep Magento's storefront checkout session synchronized with quote mutations performed by
     * the agent. A fresh guest quote can receive its database id only when the repository saves it;
     * without reflecting that id back into Checkout\Model\Session, minicart/checkout requests may
     * continue reading a different empty quote even though the agent response says the item was added.
     */
    private function syncStorefrontSession(array $identity, Quote $quote): void
    {
        if (($identity['channel'] ?? '') !== 'storefront') {
            return;
        }
        if ((int)$quote->getId() > 0) {
            $this->checkoutSession->setQuoteId((int)$quote->getId());
        }
        $this->checkoutSession->setCartWasUpdated(true);
    }

    private function presentQuote(Quote $quote, ?string $cartId): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (int)$item->getItemId(),
                'sku' => (string)$item->getSku(),
                'name' => (string)$item->getName(),
                'qty' => (float)$item->getQty(),
                'row_total' => (float)$item->getRowTotal(),
                'formatted_row_total' => $this->priceCurrency->format((float)$item->getRowTotal(), false),
                'options' => $this->presentItemOptions($item),
            ];
        }
        $totalsAddress = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $discount = abs((float)$totalsAddress->getDiscountAmount());
        $shipping = $quote->isVirtual() ? 0.0 : (float)$quote->getShippingAddress()->getShippingAmount();
        $tax = (float)$totalsAddress->getTaxAmount();
        return [
            'cart_id' => $cartId ?: '',
            'items_count' => (int)$quote->getItemsQty(),
            'subtotal' => (float)$quote->getSubtotal(),
            'formatted_subtotal' => $this->priceCurrency->format((float)$quote->getSubtotal(), false),
            'shipping_amount' => $shipping,
            'formatted_shipping_amount' => $this->priceCurrency->format($shipping, false),
            'tax_amount' => $tax,
            'formatted_tax_amount' => $this->priceCurrency->format($tax, false),
            'grand_total' => (float)$quote->getGrandTotal(),
            'formatted_grand_total' => $this->priceCurrency->format((float)$quote->getGrandTotal(), false),
            'coupon_code' => (string)$quote->getCouponCode(),
            'discount_amount' => $discount,
            'formatted_discount_amount' => $this->priceCurrency->format($discount, false),
            'items' => $items,
        ];
    }

    private function presentItemOptions($item): array
    {
        $result = [];
        $option = $item->getOptionByCode('info_buyRequest');
        if (!$option) return $result;
        $data = json_decode((string)$option->getValue(), true);
        if (!is_array($data)) {
            try { $data = unserialize((string)$option->getValue(), ['allowed_classes' => false]); } catch (\Throwable) { $data = []; }
        }
        foreach (['super_attribute','options','bundle_option','links','super_group'] as $key) {
            if (!empty($data[$key])) $result[] = ['code'=>$key,'value'=>json_encode($data[$key], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
        }
        return $result;
    }

    private function emptySummary(?string $cartId): array
    {
        return [
            'cart_id' => $cartId ?: '',
            'items_count' => 0,
            'subtotal' => 0.0,
            'formatted_subtotal' => $this->priceCurrency->format(0.0, false),
            'shipping_amount' => 0.0,
            'formatted_shipping_amount' => $this->priceCurrency->format(0.0, false),
            'tax_amount' => 0.0,
            'formatted_tax_amount' => $this->priceCurrency->format(0.0, false),
            'grand_total' => 0.0,
            'formatted_grand_total' => $this->priceCurrency->format(0.0, false),
            'coupon_code' => '',
            'discount_amount' => 0.0,
            'formatted_discount_amount' => $this->priceCurrency->format(0.0, false),
            'items' => [],
        ];
    }
}
