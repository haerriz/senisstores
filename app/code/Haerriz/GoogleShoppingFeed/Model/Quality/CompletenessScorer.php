<?php
namespace Haerriz\GoogleShoppingFeed\Model\Quality;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductTypeResolverInterface;
use Haerriz\GoogleShoppingFeed\Model\Mapping\RowBuilder;
use Haerriz\GoogleShoppingFeed\Model\RuleFactory;

class CompletenessScorer
{
    private const CHECK_FIELDS = ['image', 'link', 'price', 'brand', 'gtin', 'mpn', 'description'];
    private const CRITICAL_FIELDS = ['image', 'link', 'price'];
    private const GUIDANCE = [
        'image' => 'Set the base image or map image_link to a valid product image URL.',
        'link' => 'Map link to product_url and confirm store URLs are enabled.',
        'price' => 'Map price to the product price resolver and confirm currency is configured.',
        'brand' => 'Set manufacturer or brand attribute, or map g:brand to your brand field.',
        'gtin' => 'Map GTIN to UPC/EAN/GTIN where available.',
        'mpn' => 'Map MPN to manufacturer part number where available.',
        'description' => 'Map description or short_description with HTML stripping if needed.',
    ];

    private ProductProviderInterface $productProvider;
    private ProductTypeResolverInterface $productTypeResolver;
    private RowBuilder $rowBuilder;
    private RuleFactory $ruleFactory;

    public function __construct(
        ProductProviderInterface $productProvider,
        ProductTypeResolverInterface $productTypeResolver,
        RowBuilder $rowBuilder,
        RuleFactory $ruleFactory
    ) {
        $this->productProvider = $productProvider;
        $this->productTypeResolver = $productTypeResolver;
        $this->rowBuilder = $rowBuilder;
        $this->ruleFactory = $ruleFactory;
    }

    /**
     * Score a profile sample for required shopping attributes.
     *
     * @return array{
     *   score: float,
     *   checked: int,
     *   complete: int,
     *   missing: array<string, array<int, string>>,
     *   field_missing_counts: array<string, int>,
     *   critical_missing_counts: array<string, int>,
     *   guidance: array<string, string>
     * }
     */
    public function score(FeedProfileInterface $profile, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $rule = $this->createRule($profile);
        $collection = $this->productProvider->getCollection($profile, $rule, 0, max($limit * 3, 15));
        $this->productTypeResolver->prepare($collection, $profile);

        $checked = 0;
        $complete = 0;
        $missing = [
            'image' => [],
            'link' => [],
            'price' => [],
            'brand' => [],
            'gtin' => [],
            'mpn' => [],
            'description' => [],
        ];

        foreach ($collection as $product) {
            foreach ($this->productTypeResolver->resolve($product, $profile) as $feedProduct) {
                if ($rule && !$rule->getConditions()->validate($feedProduct)) {
                    continue;
                }

                try {
                    $row = $this->rowBuilder->build($feedProduct, $profile);
                } catch (\Throwable $e) {
                    continue;
                }

                $sku = (string)($feedProduct->getSku() ?: ($row['g:id'] ?? $row['id'] ?? ''));
                $checked++;
                $rowMissing = $this->findMissingFields($row, $feedProduct);

                if ($rowMissing === []) {
                    $complete++;
                } else {
                    foreach ($rowMissing as $field) {
                        $missing[$field][] = $sku;
                    }
                }

                if ($checked >= $limit) {
                    break 2;
                }
            }
        }

        $fieldMissingCounts = [];
        foreach (self::CHECK_FIELDS as $field) {
            $fieldMissingCounts[$field] = count($missing[$field]);
        }
        $criticalMissingCounts = [];
        foreach (self::CRITICAL_FIELDS as $field) {
            $criticalMissingCounts[$field] = $fieldMissingCounts[$field] ?? 0;
        }

        $score = $checked > 0 ? round(($complete / $checked) * 100, 1) : 0.0;

        return [
            'score' => $score,
            'checked' => $checked,
            'complete' => $complete,
            'missing' => $missing,
            'field_missing_counts' => $fieldMissingCounts,
            'critical_missing_counts' => $criticalMissingCounts,
            'guidance' => self::GUIDANCE,
        ];
    }

    /**
     * Flatten missing SKUs into CSV-friendly rows.
     *
     * @return array<int, array{sku: string, field: string, severity: string, guidance: string}>
     */
    public function toReportRows(FeedProfileInterface $profile, int $limit = 500): array
    {
        $result = $this->score($profile, $limit);
        $rows = [];
        foreach ($result['missing'] as $field => $skus) {
            foreach ($skus as $sku) {
                $rows[] = [
                    'sku' => $sku,
                    'field' => $field,
                    'severity' => in_array($field, self::CRITICAL_FIELDS, true) ? 'critical' : 'warning',
                    'guidance' => self::GUIDANCE[$field] ?? '',
                ];
            }
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return string[]
     */
    private function findMissingFields(array $row, $product): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower(str_replace(['g:', ' '], ['', '_'], (string)$key))] = $value;
        }

        $missing = [];
        foreach (self::CHECK_FIELDS as $field) {
            $value = $normalized[$field] ?? null;
            if ($field === 'image') {
                $value = $value
                    ?? $normalized['image_link']
                    ?? $normalized['image_url']
                    ?? $product->getImage();
                if (!$value || $value === 'no_selection') {
                    $missing[] = $field;
                }
                continue;
            }
            if ($field === 'link') {
                $value = $value
                    ?? $normalized['product_url']
                    ?? $normalized['url']
                    ?? $product->getProductUrl();
            }
            if ($field === 'price') {
                $value = $value
                    ?? $normalized['sale_price']
                    ?? $product->getFinalPrice()
                    ?? $product->getPrice();
            }
            if ($field === 'brand') {
                $value = $value
                    ?? $normalized['brand']
                    ?? $product->getData('brand')
                    ?? $product->getData('manufacturer');
            }
            if ($field === 'gtin') {
                $value = $value
                    ?? $normalized['gtin']
                    ?? $product->getData('gtin')
                    ?? $product->getData('ean')
                    ?? $product->getData('upc');
            }
            if ($field === 'mpn') {
                $value = $value
                    ?? $normalized['mpn']
                    ?? $product->getData('mpn')
                    ?? $product->getData('manufacturer_part_number');
            }
            if ($field === 'description') {
                $value = $value
                    ?? $normalized['description']
                    ?? $product->getData('description')
                    ?? $product->getData('short_description');
            }

            if ($value === null || trim((string)$value) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function createRule(FeedProfileInterface $profile)
    {
        $serialized = $profile->getConditionsSerialized();
        if (!$serialized) {
            return null;
        }
        $conditions = json_decode($serialized, true);
        if (!$conditions) {
            return null;
        }
        $rule = $this->ruleFactory->create();
        $rule->getConditions()->loadArray($conditions);
        return $rule;
    }
}
