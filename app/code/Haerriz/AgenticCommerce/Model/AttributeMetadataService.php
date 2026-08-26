<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\StoreManagerInterface;

class AttributeMetadataService
{
    private const CACHE_PREFIX = 'agentic_attr_meta_';

    public function __construct(
        private CollectionFactory $attributeCollectionFactory,
        private StoreManagerInterface $storeManager,
        private Config $config,
        private CacheInterface $cache
    ) {
    }

    public function getMetadata(?string $search = null, ?int $storeId = null): array
    {
        $storeId ??= (int)$this->storeManager->getStore()->getId();
        $needle = mb_strtolower(trim((string)$search));
        $allowList = $this->config->getExposedAttributes($storeId);
        // Attribute exposure is configurable per store. Include the effective allow-list in the
        // cache key so an Admin configuration change cannot keep stale metadata alive for an hour.
        $cacheKey = self::CACHE_PREFIX . $storeId . '_' . sha1(implode(',', $allowList));
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $items = json_decode($cached, true);
            if (is_array($items)) {
                return $this->filterMetadata($items, $needle);
            }
        }

        $collection = $this->attributeCollectionFactory->create();
        $collection->addStoreLabel($storeId);
        $collection->addVisibleFilter();
        $items = [];

        foreach ($collection as $attribute) {
            $code = (string)$attribute->getAttributeCode();
            if ($code === '' || in_array($code, ['media_gallery', 'tier_price'], true)) {
                continue;
            }
            $explicit = $allowList !== [] && in_array($code, $allowList, true);
            $storefrontRelevant = (bool)$attribute->getIsFilterable()
                || (bool)$attribute->getIsFilterableInSearch()
                || (bool)$attribute->getIsSearchable()
                || (bool)$attribute->getIsVisibleOnFront()
                || (bool)$attribute->getUsedInProductListing();
            if (!$explicit && ($allowList !== [] || !$storefrontRelevant)) {
                continue;
            }

            $options = [];
            $frontendInput = (string)$attribute->getFrontendInput();
            if (in_array($frontendInput, ['select', 'multiselect', 'boolean'], true)) {
                try {
                    foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                        $value = (string)($option['value'] ?? '');
                        $label = trim((string)($option['label'] ?? ''));
                        if ($value !== '' && $label !== '') {
                            $options[] = ['value' => $value, 'label' => $label];
                        }
                    }
                } catch (\Throwable) {
                    $options = [];
                }
            }

            $items[] = [
                'code' => $code,
                'label' => (string)($attribute->getStoreLabel($storeId) ?: $attribute->getFrontendLabel() ?: $code),
                'frontend_input' => $frontendInput ?: 'text',
                'is_filterable' => (bool)$attribute->getIsFilterable(),
                'is_filterable_in_search' => (bool)$attribute->getIsFilterableInSearch(),
                'is_searchable' => (bool)$attribute->getIsSearchable(),
                'is_visible_on_front' => (bool)$attribute->getIsVisibleOnFront(),
                'used_in_product_listing' => (bool)$attribute->getUsedInProductListing(),
                'options' => $options,
            ];
        }

        usort($items, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));
        $this->cache->save((string)json_encode($items), $cacheKey, ['EAV'], 3600);
        return $this->filterMetadata($items, $needle);
    }

    public function getByCode(string $code, ?int $storeId = null): ?array
    {
        foreach ($this->getMetadata(null, $storeId) as $item) {
            if ($item['code'] === $code) {
                return $item;
            }
        }
        return null;
    }

    public function resolveOption(string $attributeCode, string $valueOrLabel, ?int $storeId = null): string
    {
        $meta = $this->getByCode($attributeCode, $storeId);
        if ($meta === null) {
            return $valueOrLabel;
        }
        foreach ($meta['options'] as $option) {
            if ((string)$option['value'] === (string)$valueOrLabel
                || mb_strtolower((string)$option['label']) === mb_strtolower(trim($valueOrLabel))) {
                return (string)$option['value'];
            }
        }
        return $valueOrLabel;
    }

    /**
     * Backwards-compatible alias for attributes selected for product-card output.
     */
    public function getOutputAttributeCodes(?int $storeId = null): array
    {
        return $this->getDisplayAttributeCodes($storeId);
    }

    public function getDisplayAttributeCodes(?int $storeId = null): array
    {
        $limit = $this->config->getCustomAttributeLimit($storeId);
        $allow = $this->config->getDisplayAttributes($storeId);
        $hidden = array_fill_keys(array_merge($this->defaultHiddenAttributes(), $this->config->getHiddenAttributes($storeId)), true);
        $codes = [];

        foreach ($this->getMetadata(null, $storeId) as $item) {
            $code = (string)$item['code'];
            if ($code === '' || isset($hidden[$code])) {
                continue;
            }
            if ($allow !== [] && !in_array($code, $allow, true)) {
                continue;
            }
            if ($allow === [] && !($item['is_visible_on_front'] || $item['used_in_product_listing'])) {
                continue;
            }
            if ($this->looksTechnical((string)$item['label'], $code)) {
                continue;
            }
            $codes[] = $code;
            if (count($codes) >= $limit) {
                break;
            }
        }
        return $codes;
    }

    public function getSearchTextAttributeCodes(?int $storeId = null): array
    {
        $codes = ['name', 'sku'];
        foreach ($this->getMetadata(null, $storeId) as $item) {
            if (empty($item['is_searchable'])) {
                continue;
            }
            if (!in_array((string)$item['frontend_input'], ['text', 'textarea'], true)) {
                continue;
            }
            $code = (string)$item['code'];
            if ($code !== '') {
                $codes[] = $code;
            }
            if (count($codes) >= 8) {
                break;
            }
        }
        return array_values(array_unique($codes));
    }

    private function defaultHiddenAttributes(): array
    {
        return [
            'status', 'visibility', 'tax_class_id', 'category_ids', 'options_container',
            'required_options', 'has_options', 'msrp_display_actual_price_type',
            'enable_googlecheckout', 'gift_message_available', 'custom_design',
            'custom_design_from', 'custom_design_to', 'custom_layout_update',
            'page_layout', 'enable_product', 'display_actual_price',
        ];
    }

    private function looksTechnical(string $label, string $code): bool
    {
        $haystack = mb_strtolower(trim($label . ' ' . str_replace('_', ' ', $code)));
        return (bool)preg_match(
            '/\b(?:enable product|display actual price|use config|tax class|visibility|required options?|has options?|custom design|page layout)\b/u',
            $haystack
        );
    }

    private function filterMetadata(array $items, string $needle): array
    {
        if ($needle === '') {
            return $items;
        }
        return array_values(array_filter($items, static function (array $item) use ($needle): bool {
            if (str_contains(mb_strtolower($item['code'] . ' ' . $item['label']), $needle)) {
                return true;
            }
            foreach ($item['options'] as $option) {
                if (str_contains(mb_strtolower((string)$option['label']), $needle)) {
                    return true;
                }
            }
            return false;
        }));
    }
}
