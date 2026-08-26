<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;
use Haerriz\AgenticCommerce\Model\ProductPresenter;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Aggregates shopper-facing PDP state from Magento domain services.
 * Raw EAV rows/internal flags are intentionally excluded; every sub-service preserves storefront
 * scoping, pricing, inventory and extension semantics.
 */
class ProductExperienceService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private ProductPresenter $presenter,
        private InventoryService $inventory,
        private PriceInsightService $prices,
        private ProductOptionService $options,
        private ReviewService $reviews
    ) {}

    public function get(string $sku, int $storeId, float $requestedQty = 1.0, int $reviewLimit = 3, ?int $customerGroupId = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new LocalizedException(__('A product SKU is required.'));
        }
        $product = $this->products->get($sku, false, $storeId, true);
        if ($customerGroupId !== null) {
            $product->setData('customer_group_id', max(0, $customerGroupId));
        }
        $presented = $this->presenter->present($product);
        $inventory = $this->inventory->getForProduct($product, $storeId, $requestedQty);
        $price = $this->prices->get($sku, $storeId, $customerGroupId);
        $options = $this->options->describe($sku, $storeId);
        try {
            $reviews = $this->reviews->list($sku, $storeId, max(1, min(10, $reviewLimit)));
        } catch (\Throwable) {
            $reviews = ['sku'=>$sku,'total_count'=>0,'items'=>[]];
        }
        $categories = [];
        foreach (array_slice(array_values(array_unique(array_map('intval', (array)$product->getCategoryIds()))), 0, 12) as $categoryId) {
            if ($categoryId <= 0) continue;
            try {
                $category = $this->categories->get($categoryId, $storeId);
                if (!$category->getIsActive()) continue;
                $categories[] = ['id'=>(int)$category->getId(),'name'=>(string)$category->getName(),'url'=>(string)$category->getUrl()];
            } catch (\Throwable) {
                // Ignore stale category assignments rather than failing the PDP experience.
            }
        }

        $summary = [(string)$product->getName() . '.'];
        $summary[] = (string)$price['message'];
        $summary[] = (string)$inventory['message'];
        if (!empty($options['requires_options'])) {
            $summary[] = !empty($options['chat_supported'])
                ? (string)__('It has selectable options that can be configured in chat.')
                : (string)__('It has an option that must be completed on the product page.');
        }
        if ((int)($reviews['total_count'] ?? 0) > 0) {
            $summary[] = (string)__('%1 approved review(s) are available.', (int)$reviews['total_count']);
        }

        return [
            'product' => $presented,
            'short_description' => $this->plainText((string)$product->getData('short_description'), 1200),
            'description' => $this->plainText((string)$product->getData('description'), 4000),
            'categories' => $categories,
            'inventory' => $inventory,
            'price' => $price,
            'options' => $options,
            'reviews' => $reviews,
            'assistant_message' => trim(implode(' ', $summary)),
        ];
    }

    private function plainText(string $value, int $limit): string
    {
        if ($value === '') return '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        return mb_substr($value, 0, $limit);
    }
}
