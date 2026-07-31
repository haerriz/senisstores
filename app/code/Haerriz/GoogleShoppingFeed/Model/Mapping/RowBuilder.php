<?php
namespace Haerriz\GoogleShoppingFeed\Model\Mapping;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\ModifierPipelineInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductValueResolverInterface;
use Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader;
use Haerriz\GoogleShoppingFeed\Model\Template\PresetRegistry;
use Magento\Catalog\Model\Product;

class RowBuilder
{
    private $valueResolver;
    private $modifierPipeline;
    private $configReader;
    private $presetRegistry;

    public function __construct(
        ProductValueResolverInterface $valueResolver,
        ModifierPipelineInterface $modifierPipeline,
        ProfileConfigReader $configReader,
        PresetRegistry $presetRegistry
    ) {
        $this->valueResolver = $valueResolver;
        $this->modifierPipeline = $modifierPipeline;
        $this->configReader = $configReader;
        $this->presetRegistry = $presetRegistry;
    }

    public function getMappings(FeedProfileInterface $profile)
    {
        $mappings = json_decode((string)$profile->getAttributesMappingSerialized(), true);
        if (!is_array($mappings) || empty($mappings)) {
            $mappings = $this->getDefaultMappingForType((string)$profile->getFeedType());
        }
        $chains = json_decode((string)$this->configReader->get($profile, 'modifier_chains_serialized', '[]'), true);
        $chains = is_array($chains) ? $chains : [];
        foreach ($mappings as $index => &$mapping) {
            if (!isset($mapping['modifiers'])) {
                $field = (string)($mapping['google_attribute'] ?? '');
                $mapping['modifiers'] = $chains[$field] ?? $chains[$index] ?? [];
                if (!$mapping['modifiers'] && !empty($mapping['modifier'])) {
                    $mapping['modifiers'] = [['code' => 'legacy', 'value' => $mapping['modifier']]];
                }
            }
        }
        unset($mapping);
        return $mappings;
    }

    private function getDefaultMappingForType(string $feedType): array
    {
        $presets = $this->presetRegistry->getPresets();
        foreach ($presets as $key => $preset) {
            if (str_contains($feedType, $key)) {
                return $preset['mapping'] ?? [];
            }
        }
        return [
            ['google_attribute' => 'g:id', 'magento_attribute' => 'sku'],
            ['google_attribute' => 'g:title', 'magento_attribute' => 'name'],
            ['google_attribute' => 'g:price', 'magento_attribute' => 'price'],
            ['google_attribute' => 'g:availability', 'magento_attribute' => 'quantity']
        ];
    }

    public function build(Product $product, FeedProfileInterface $profile)
    {
        $row = [];
        $conditionalValues = json_decode(
            (string)$this->configReader->get($profile, 'conditional_values_serialized', '[]'),
            true
        );
        $conditionalValues = is_array($conditionalValues) ? $conditionalValues : [];
        foreach ($this->getMappings($profile) as $mapping) {
            $field = (string)($mapping['google_attribute'] ?? $mapping['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $effectiveMapping = $this->applyConditionalValue(
                $mapping,
                (array)($conditionalValues[$field] ?? []),
                $product
            );
            $value = $this->valueResolver->resolve($effectiveMapping, $product, $profile);
            $row[$field] = $this->modifierPipeline->apply(
                $value,
                (array)($mapping['modifiers'] ?? []),
                $product,
                $profile
            );
        }
        return $row;
    }

    private function applyConditionalValue(array $mapping, array $conditions, Product $product)
    {
        foreach ($conditions as $condition) {
            $actual = $product->getData((string)($condition['attribute'] ?? ''));
            $expected = $condition['value'] ?? null;
            $operator = (string)($condition['operator'] ?? 'eq');
            if ($this->matches($actual, $operator, $expected)) {
                if (array_key_exists('static_value', $condition)) {
                    $mapping['source_type'] = 'static';
                    $mapping['static_value'] = $condition['static_value'];
                } elseif (!empty($condition['magento_attribute'])) {
                    $mapping['magento_attribute'] = $condition['magento_attribute'];
                }
                break;
            }
        }
        return $mapping;
    }

    private function matches($actual, $operator, $expected)
    {
        switch ($operator) {
            case 'eq':
                return (string)$actual === (string)$expected;
            case 'neq':
                return (string)$actual !== (string)$expected;
            case 'gt':
                return (float)$actual > (float)$expected;
            case 'gte':
                return (float)$actual >= (float)$expected;
            case 'lt':
                return (float)$actual < (float)$expected;
            case 'lte':
                return (float)$actual <= (float)$expected;
            case 'contains':
                return mb_stripos((string)$actual, (string)$expected) !== false;
            default:
                return false;
        }
    }

    public function validate(FeedProfileInterface $profile)
    {
        $errors = [];
        $fields = [];
        foreach ($this->getMappings($profile) as $mapping) {
            $field = (string)($mapping['google_attribute'] ?? $mapping['field'] ?? '');
            if ($field === '' || !preg_match('/^(?:g:)?[A-Za-z_][A-Za-z0-9_.-]*$/', $field)) {
                $errors[] = 'Invalid output field name: ' . $field;
            } elseif (isset($fields[$field])) {
                $errors[] = 'Duplicate output field: ' . $field;
            }
            $fields[$field] = true;
            try {
                $this->modifierPipeline->validate((array)($mapping['modifiers'] ?? []));
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $field . ': ' . $exception->getMessage();
            }
        }
        return $errors;
    }
}
