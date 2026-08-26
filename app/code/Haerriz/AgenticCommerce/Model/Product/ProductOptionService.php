<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Converts Magento product-type/custom-option configuration into a storefront-neutral schema
 * and validates structured selections before they ever reach Quote::addProduct().
 */
class ProductOptionService
{
    public function __construct(private ProductRepositoryInterface $productRepository) {}

    public function describe(string $sku, int $storeId): array
    {
        /** @var Product $product */
        $product = $this->productRepository->get($sku, false, $storeId, true);
        $type = (string)$product->getTypeId();
        $groups = [];

        foreach ($product->getOptions() ?: [] as $option) {
            $optionType = (string)$option->getType();
            $values = [];
            $rawValues = $option->getValues();
            if (is_iterable($rawValues)) {
                foreach ($rawValues as $value) {
                    $values[] = [
                        'value' => (string)$value->getOptionTypeId(),
                        'label' => (string)$value->getTitle(),
                        'price' => (float)$value->getPrice(),
                        'price_type' => (string)$value->getPriceType(),
                        'sku' => '',
                    ];
                }
            }
            $inputMode = match ($optionType) {
                'drop_down', 'radio' => 'select',
                'multiple', 'checkbox' => 'multi',
                'field' => 'text',
                'area' => 'textarea',
                'date', 'date_time', 'time' => 'text',
                'file' => 'file',
                default => $values ? 'select' : 'text',
            };
            $groups[] = [
                'code' => 'custom_option:' . (int)$option->getOptionId(),
                'attribute_code' => '',
                'label' => (string)$option->getTitle(),
                'type' => 'custom_option',
                'input_mode' => $inputMode,
                'required' => (bool)$option->getIsRequire(),
                'multiple' => in_array($inputMode, ['multi'], true),
                'chat_supported' => $inputMode !== 'file',
                'values' => $values,
            ];
        }

        if ($type === 'configurable') {
            foreach ($product->getTypeInstance()->getConfigurableAttributes($product) as $attribute) {
                $eav = $attribute->getProductAttribute();
                if (!$eav) continue;
                $values = [];
                $rawOptions = $attribute->getOptions();
                if (is_iterable($rawOptions)) {
                    foreach ($rawOptions as $opt) {
                        $value = (string)($opt['value_index'] ?? '');
                        if ($value === '') continue;
                        $values[] = [
                            'value' => $value,
                            'label' => (string)($opt['label'] ?? $value),
                            'price' => 0.0,
                            'price_type' => '',
                            'sku' => '',
                        ];
                    }
                }
                $groups[] = [
                    'code' => 'super_attribute:' . (int)$eav->getAttributeId(),
                    'attribute_code' => (string)$eav->getAttributeCode(),
                    'label' => (string)($attribute->getLabel() ?: $eav->getStoreLabel()),
                    'type' => 'configurable',
                    'input_mode' => 'select',
                    'required' => true,
                    'multiple' => false,
                    'chat_supported' => true,
                    'values' => $values,
                ];
            }
        } elseif ($type === 'bundle') {
            $options = $product->getTypeInstance()->getOptionsCollection($product);
            $ids = [];
            foreach ($options as $option) $ids[] = (int)$option->getOptionId();
            $selections = $ids ? $product->getTypeInstance()->getSelectionsCollection($ids, $product) : [];
            $byOption = [];
            foreach ($selections as $selection) {
                $byOption[(int)$selection->getOptionId()][] = [
                    'value' => (string)$selection->getSelectionId(),
                    'label' => (string)$selection->getName(),
                    'price' => (float)$selection->getPrice(),
                    'price_type' => (string)$selection->getSelectionPriceType(),
                    'sku' => (string)$selection->getSku(),
                ];
            }
            foreach ($options as $option) {
                $id = (int)$option->getOptionId();
                $input = in_array((string)$option->getType(), ['multi', 'checkbox'], true) ? 'multi' : 'select';
                $groups[] = [
                    'code' => 'bundle_option:' . $id,
                    'attribute_code' => '',
                    'label' => (string)$option->getTitle(),
                    'type' => 'bundle',
                    'input_mode' => $input,
                    'required' => (bool)$option->getRequired(),
                    'multiple' => $input === 'multi',
                    'chat_supported' => true,
                    'values' => $byOption[$id] ?? [],
                ];
            }
        } elseif ($type === 'grouped') {
            foreach ($product->getTypeInstance()->getAssociatedProducts($product) as $child) {
                if (!$child->isSalable()) continue;
                $groups[] = [
                    'code' => 'grouped:' . (int)$child->getId(),
                    'attribute_code' => '',
                    'label' => (string)$child->getName(),
                    'type' => 'grouped',
                    'input_mode' => 'quantity',
                    'required' => false,
                    'multiple' => false,
                    'chat_supported' => true,
                    'values' => [[
                        'value' => '1',
                        'label' => (string)$child->getSku(),
                        'price' => (float)$child->getFinalPrice(),
                        'price_type' => 'fixed',
                        'sku' => (string)$child->getSku(),
                    ]],
                ];
            }
        } elseif ($type === 'downloadable' && method_exists($product->getTypeInstance(), 'getLinks')) {
            $values = [];
            $links = $product->getTypeInstance()->getLinks($product);
            if (is_iterable($links)) {
                foreach ($links as $link) {
                    $values[] = [
                        'value' => (string)$link->getId(),
                        'label' => (string)$link->getTitle(),
                        'price' => (float)$link->getPrice(),
                        'price_type' => 'fixed',
                        'sku' => '',
                    ];
                }
            }
            if ($values) {
                $groups[] = [
                    'code' => 'downloadable_links',
                    'attribute_code' => '',
                    'label' => (string)__('Download links'),
                    'type' => 'downloadable',
                    'input_mode' => 'multi',
                    'required' => true,
                    'multiple' => true,
                    'chat_supported' => true,
                    'values' => $values,
                ];
            }
        }

        $requires = $this->requiresOptions($product, $groups);
        $chatSupported = true;
        foreach ($groups as $group) {
            if (!empty($group['required']) && empty($group['chat_supported'])) {
                $chatSupported = false;
                break;
            }
        }
        return [
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'type' => $type,
            'requires_options' => $requires,
            'chat_supported' => $chatSupported,
            'groups' => $groups,
        ];
    }

    /**
     * @return array{selections:array<int,array{code:string,values:array<int,string>}>,missing:array<int,string>}
     */
    public function normalizeSelections(array $schema, array $selections): array
    {
        $groups = [];
        foreach ((array)($schema['groups'] ?? []) as $group) {
            if (is_array($group) && !empty($group['code'])) $groups[(string)$group['code']] = $group;
        }
        $provided = [];
        foreach ($selections as $selection) {
            if (!is_array($selection)) continue;
            $code = trim((string)($selection['code'] ?? ''));
            if ($code === '' || !isset($groups[$code])) {
                throw new LocalizedException(__('An invalid product option was supplied.'));
            }
            $values = $selection['values'] ?? ($selection['value'] ?? []);
            $values = is_array($values) ? $values : [$values];
            $values = array_values(array_filter(array_map(static fn($v): string => mb_substr(trim((string)$v), 0, 1000), $values), static fn(string $v): bool => $v !== ''));
            if ($values === []) continue;
            $group = $groups[$code];
            if (empty($group['chat_supported'])) {
                throw new LocalizedException(__('This product option must be completed on the product page.'));
            }
            $allowed = [];
            foreach ((array)($group['values'] ?? []) as $value) {
                if (is_array($value)) $allowed[(string)($value['value'] ?? '')] = true;
            }
            $inputMode = (string)($group['input_mode'] ?? 'select');
            if ($allowed && !in_array($inputMode, ['text', 'textarea', 'quantity'], true)) {
                foreach ($values as $value) {
                    if (!isset($allowed[$value])) throw new LocalizedException(__('A selected value is not available for %1.', (string)($group['label'] ?? $code)));
                }
            }
            if (!$group['multiple'] && !in_array($inputMode, ['quantity'], true)) $values = [reset($values)];
            if ($inputMode === 'quantity') {
                $qty = max(0.0, min(100.0, (float)$values[0]));
                $values = [(string)$qty];
            }
            $provided[$code] = ['code' => $code, 'values' => $values];
        }

        $missing = [];
        foreach ($groups as $code => $group) {
            if (!empty($group['required']) && empty($provided[$code]['values'])) $missing[] = (string)($group['label'] ?? $code);
        }
        if ((string)($schema['type'] ?? '') === 'grouped') {
            $positive = false;
            foreach ($provided as $row) if (str_starts_with($row['code'], 'grouped:') && (float)($row['values'][0] ?? 0) > 0) { $positive = true; break; }
            if (!$positive) $missing[] = (string)__('at least one grouped product quantity');
        }
        return ['selections' => array_values($provided), 'missing' => array_values(array_unique($missing))];
    }

    public function buildBuyRequest(Product $product, float $qty, array $selections): DataObject
    {
        $data = ['qty' => $qty];
        $custom = []; $super = []; $bundle = []; $bundleQty = []; $superGroup = []; $links = [];
        foreach ($selections as $selection) {
            if (!is_array($selection)) continue;
            $code = trim((string)($selection['code'] ?? ''));
            $values = $selection['values'] ?? [];
            $values = is_array($values) ? array_values(array_map('strval', $values)) : [(string)$values];
            if ($code === '' || !$values) continue;
            if (str_starts_with($code, 'custom_option:')) $custom[(int)substr($code, 14)] = count($values) === 1 ? $values[0] : $values;
            elseif (str_starts_with($code, 'super_attribute:')) $super[(int)substr($code, 16)] = $values[0];
            elseif (str_starts_with($code, 'bundle_option:')) $bundle[(int)substr($code, 14)] = count($values) === 1 ? $values[0] : $values;
            elseif (str_starts_with($code, 'bundle_qty:')) $bundleQty[(int)substr($code, 11)] = max(1.0, (float)$values[0]);
            elseif (str_starts_with($code, 'grouped:')) $superGroup[(int)substr($code, 8)] = max(0.0, (float)$values[0]);
            elseif ($code === 'downloadable_links') $links = array_values(array_map('intval', $values));
        }
        if ($custom) $data['options'] = $custom;
        if ($super) $data['super_attribute'] = $super;
        if ($bundle) $data['bundle_option'] = $bundle;
        if ($bundleQty) $data['bundle_option_qty'] = $bundleQty;
        if ($superGroup) $data['super_group'] = $superGroup;
        if ($links) $data['links'] = $links;
        return new DataObject($data);
    }

    private function requiresOptions(Product $product, array $groups): bool
    {
        if ((string)$product->getTypeId() === 'grouped') return true;
        foreach ($groups as $group) if (!empty($group['required'])) return true;
        return !in_array((string)$product->getTypeId(), ['simple', 'virtual'], true) && $groups !== [];
    }
}
