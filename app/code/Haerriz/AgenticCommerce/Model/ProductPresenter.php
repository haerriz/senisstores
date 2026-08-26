<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;

class ProductPresenter
{
    public function __construct(
        private ImageHelper $imageHelper,
        private PriceCurrencyInterface $priceCurrency,
        private AttributeMetadataService $metadataService,
        private InventoryService $inventoryService,
        private Config $config
    ) {
    }

    public function present(ProductInterface $product): array
    {
        /** @var Product $product */
        $regular = (float)$product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
        $final = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        $custom = [];
        foreach ($this->metadataService->getDisplayAttributeCodes((int)$product->getStoreId()) as $code) {
            if (in_array($code, ['name', 'price', 'sku', 'image', 'small_image', 'thumbnail'], true)) {
                continue;
            }
            $attribute = $product->getResource()->getAttribute($code);
            if (!$attribute) {
                continue;
            }
            try {
                $display = $attribute->getFrontend()->getValue($product);
            } catch (\Throwable) {
                $display = $product->getData($code);
            }
            if (is_array($display)) {
                $display = implode(', ', array_map('strval', $display));
            }
            $display = trim((string)$display);
            if ($display === '' || $display === 'No') {
                continue;
            }
            $custom[] = [
                'code' => $code,
                'label' => (string)($attribute->getStoreLabel((int)$product->getStoreId()) ?: $attribute->getFrontendLabel() ?: $code),
                'value' => $display,
            ];
        }
        try {
            $image = $this->imageHelper->init($product, 'category_page_grid')->getUrl();
        } catch (\Throwable) {
            $image = '';
        }
        $inventory = null;
        if ($this->config->isInventoryOnCardsEnabled((int)$product->getStoreId())) {
            try {
                $inventory = $this->inventoryService->getForProduct($product, (int)$product->getStoreId());
            } catch (\Throwable) {
                // Keep the product result usable if an inventory extension fails. A null inventory
                // object is safer than returning a partial object that violates the GraphQL contract.
                $inventory = null;
            }
        }
        return [
            'id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'url' => (string)$product->getProductUrl(),
            'image' => $image,
            'price' => $final,
            'regular_price' => $regular,
            'formatted_price' => $this->priceCurrency->format($final, false),
            'formatted_regular_price' => $this->priceCurrency->format($regular, false),
            'type' => (string)$product->getTypeId(),
            'is_salable' => (bool)($inventory['is_salable'] ?? $product->isSalable()),
            'inventory' => $inventory,
            'match_reasons' => [],
            'custom_attributes' => $custom,
        ];
    }
}
