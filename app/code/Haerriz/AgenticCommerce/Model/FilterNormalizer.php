<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Magento\Store\Model\StoreManagerInterface;

class FilterNormalizer
{
    private const SPECIAL_ATTRIBUTES = ['price', 'sku', 'category_id', 'category_uid', 'name', 'stock_status'];
    private const CONDITIONS = ['eq', 'in', 'nin', 'match', 'from', 'to', 'range'];

    public function __construct(
        private AttributeMetadataService $metadataService,
        private StoreManagerInterface $storeManager
    ) {
    }

    public function normalize(array $filters): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $normalized = [];
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $attribute = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($filter['attribute'] ?? '')) ?: '';
            if ($attribute === '') {
                continue;
            }
            $meta = null;
            if (!in_array($attribute, self::SPECIAL_ATTRIBUTES, true)) {
                $meta = $this->metadataService->getByCode($attribute, $storeId);
                if ($meta === null || !($meta['is_filterable'] || $meta['is_filterable_in_search'] || $meta['is_searchable'])) {
                    continue;
                }
            }
            $condition = strtolower((string)($filter['condition'] ?? 'eq'));
            if (!in_array($condition, self::CONDITIONS, true)) {
                $condition = 'eq';
            }
            $rawValues = $filter['values'] ?? $filter['value'] ?? [];
            $values = is_array($rawValues) ? $rawValues : [$rawValues];
            $values = array_values(array_filter(array_map(static fn($v): string => trim(strip_tags((string)$v)), $values), static fn(string $v): bool => $v !== ''));
            if ($values === []) {
                continue;
            }
            if ($meta !== null && in_array($meta['frontend_input'], ['select', 'multiselect', 'boolean'], true)) {
                $values = array_map(fn(string $v): string => $this->metadataService->resolveOption($attribute, $v, $storeId), $values);
                if (count($values) > 1) {
                    $condition = 'in';
                }
            }
            if ($attribute === 'price') {
                $values = array_map(static fn(string $v): string => (string)(float)str_replace(',', '', $v), $values);
            }
            $normalized[$attribute] = [
                'attribute' => $attribute,
                'condition' => $condition,
                'values' => array_slice($values, 0, 20),
                'label' => (string)($filter['label'] ?? ($meta['label'] ?? ucfirst(str_replace('_', ' ', $attribute)))),
            ];
        }
        return array_values($normalized);
    }
}
