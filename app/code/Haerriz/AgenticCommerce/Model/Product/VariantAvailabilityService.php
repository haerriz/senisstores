<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

/**
 * Resolves shopper-visible configurable-product choices to child variants and their storefront-safe
 * availability. Source-level MSI quantities are intentionally never exposed; InventoryService remains
 * the single authority for salable quantity/privacy/backorder/min-max/increment semantics.
 */
class VariantAvailabilityService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductOptionService $options,
        private InventoryService $inventory,
        private PriceInsightService $prices
    ) {
    }

    public function resolve(
        string $sku,
        int $storeId,
        array $selections = [],
        string $query = '',
        float $requestedQty = 1.0,
        ?int $customerGroupId = null,
        int $limit = 12
    ): array {
        $sku = trim($sku);
        if ($sku === '') {
            throw new LocalizedException(__('A product SKU is required.'));
        }
        /** @var Product $parent */
        $parent = $this->products->get($sku, false, $storeId, true);
        $requestedQty = max(0.0001, min(10000.0, $requestedQty));

        if ((string)$parent->getTypeId() !== 'configurable') {
            $inventory = $this->inventory->getForProduct($parent, $storeId, $requestedQty);
            return [
                'parent_sku' => (string)$parent->getSku(),
                'complete' => true,
                'matched_sku' => (string)$parent->getSku(),
                'requested_qty' => $requestedQty,
                'selected' => [],
                'missing_attributes' => [],
                'candidates' => [[
                    'sku' => (string)$parent->getSku(),
                    'name' => (string)$parent->getName(),
                    'attributes' => [],
                    'inventory' => $inventory,
                    'price' => $this->prices->get((string)$parent->getSku(), $storeId, $customerGroupId),
                ]],
                'assistant_message' => (string)$inventory['message'],
            ];
        }

        $schema = $this->options->describe($sku, $storeId);
        $groups = [];
        foreach ((array)($schema['groups'] ?? []) as $group) {
            if (!is_array($group) || (string)($group['type'] ?? '') !== 'configurable') {
                continue;
            }
            $groups[(string)$group['code']] = $group;
        }
        if ($groups === []) {
            throw new LocalizedException(__('This configurable product does not expose selectable variants.'));
        }

        $selected = $this->normalizeSelections($groups, $selections);
        $selected += $this->selectionFromQuery($groups, $query, $selected);

        $attributeMeta = [];
        foreach ($groups as $code => $group) {
            $attributeId = (int)substr($code, strlen('super_attribute:'));
            if ($attributeId <= 0) {
                continue;
            }
            $valueLabels = [];
            foreach ((array)($group['values'] ?? []) as $value) {
                if (is_array($value) && (string)($value['value'] ?? '') !== '') {
                    $valueLabels[(string)$value['value']] = (string)($value['label'] ?? $value['value']);
                }
            }
            $attributeMeta[$attributeId] = [
                'code' => $code,
                'attribute_code' => (string)($group['attribute_code'] ?? ''),
                'label' => (string)($group['label'] ?? $group['attribute_code'] ?? $code),
                'values' => $valueLabels,
            ];
        }

        $candidates = [];
        $usedProducts = $parent->getTypeInstance()->getUsedProducts($parent);
        foreach ($usedProducts as $child) {
            $matches = true;
            foreach ($selected as $groupCode => $optionValue) {
                $attributeId = (int)substr($groupCode, strlen('super_attribute:'));
                $meta = $attributeMeta[$attributeId] ?? null;
                if (!$meta || (string)$meta['attribute_code'] === '') {
                    $matches = false;
                    break;
                }
                if ((string)$child->getData((string)$meta['attribute_code']) !== (string)$optionValue) {
                    $matches = false;
                    break;
                }
            }
            if (!$matches) {
                continue;
            }

            $attrs = [];
            foreach ($attributeMeta as $meta) {
                $value = (string)$child->getData((string)$meta['attribute_code']);
                $attrs[] = [
                    'code' => (string)$meta['attribute_code'],
                    'value' => (string)($meta['values'][$value] ?? $value),
                ];
            }
            try {
                $availability = $this->inventory->getForProduct($child, $storeId, $requestedQty);
            } catch (\Throwable) {
                continue;
            }
            try {
                $price = $this->prices->get((string)$child->getSku(), $storeId, $customerGroupId);
            } catch (\Throwable) {
                $price = null;
            }
            $candidates[] = [
                'sku' => (string)$child->getSku(),
                'name' => (string)$child->getName(),
                'attributes' => $attrs,
                'inventory' => $availability,
                'price' => $price,
            ];
            if (count($candidates) >= max(1, min(24, $limit))) {
                break;
            }
        }

        $missing = [];
        foreach ($groups as $code => $group) {
            if (!isset($selected[$code])) {
                $missing[] = (string)($group['label'] ?? $group['attribute_code'] ?? $code);
            }
        }
        $complete = $missing === [];
        $matchedSku = $complete && count($candidates) === 1 ? (string)$candidates[0]['sku'] : '';
        $selectedOutput = [];
        foreach ($selected as $code => $value) {
            $group = $groups[$code] ?? null;
            if (!$group) continue;
            $label = $value;
            foreach ((array)($group['values'] ?? []) as $option) {
                if (is_array($option) && (string)($option['value'] ?? '') === (string)$value) {
                    $label = (string)($option['label'] ?? $value);
                    break;
                }
            }
            $selectedOutput[] = ['code'=>(string)($group['attribute_code'] ?? $code),'value'=>$label];
        }

        if ($candidates === []) {
            $message = (string)__('That option combination is not available for %1.', (string)$parent->getName());
        } elseif ($complete && count($candidates) === 1) {
            $message = (string)($candidates[0]['inventory']['message'] ?? __('Variant availability found.'));
        } elseif ($complete) {
            $message = (string)__('I found %1 matching variants. Please choose the exact variant.', count($candidates));
        } else {
            $message = (string)__('I found %1 matching variant(s). Choose %2 to narrow it down.', count($candidates), implode(', ', $missing));
        }

        return [
            'parent_sku' => (string)$parent->getSku(),
            'complete' => $complete,
            'matched_sku' => $matchedSku,
            'requested_qty' => $requestedQty,
            'selected' => $selectedOutput,
            'missing_attributes' => $missing,
            'candidates' => $candidates,
            'assistant_message' => $message,
        ];
    }

    /** @return array<string,string> */
    private function normalizeSelections(array $groups, array $selections): array
    {
        $selected = [];
        foreach (array_slice($selections, 0, 20) as $selection) {
            if (!is_array($selection)) continue;
            $code = trim((string)($selection['code'] ?? ''));
            $values = $selection['values'] ?? ($selection['value'] ?? []);
            $values = is_array($values) ? $values : [$values];
            $value = trim((string)($values[0] ?? ''));
            if ($code === '' || $value === '' || !isset($groups[$code])) {
                continue;
            }
            $allowed = [];
            foreach ((array)($groups[$code]['values'] ?? []) as $option) {
                if (is_array($option)) $allowed[(string)($option['value'] ?? '')] = true;
            }
            if (!isset($allowed[$value])) {
                throw new LocalizedException(__('A selected variant value is not available for %1.', (string)($groups[$code]['label'] ?? $code)));
            }
            $selected[$code] = $value;
        }
        return $selected;
    }

    /** @return array<string,string> */
    private function selectionFromQuery(array $groups, string $query, array $alreadySelected): array
    {
        $haystack = $this->normalize($query);
        if ($haystack === '') return [];
        $found = [];
        foreach ($groups as $code => $group) {
            if (isset($alreadySelected[$code])) continue;
            $matches = [];
            foreach ((array)($group['values'] ?? []) as $option) {
                if (!is_array($option)) continue;
                $label = $this->normalize((string)($option['label'] ?? ''));
                if ($label === '' || mb_strlen($label) < 2) continue;
                if (preg_match('/(?:^|\s)' . preg_quote($label, '/') . '(?:$|\s)/u', $haystack)) {
                    $matches[(string)($option['value'] ?? '')] = true;
                }
            }
            // Never guess when two values in the same configurable attribute match the sentence.
            if (count($matches) === 1) {
                $found[$code] = (string)array_key_first($matches);
            }
        }
        return $found;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
