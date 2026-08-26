<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Magento\Framework\Exception\LocalizedException;
use Haerriz\AgenticCommerce\Model\Config;

/** Grounded multi-product comparison using Magento storefront projections only. */
class ProductComparisonService
{
    private const ALLOWED_FOCUS = ['description','attributes','price','inventory','reviews','options','categories'];

    public function __construct(private ProductExperienceService $experience, private Config $config) {}

    /** @param string[] $skus */
    public function compare(array $skus, int $storeId, array $focus = [], ?int $customerGroupId = null, string $goal = ''): array
    {
        $skus = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $skus))));
        $skus = array_slice($skus, 0, $this->config->getMaxComparisonProducts($storeId));
        if (count($skus) < 2) {
            throw new LocalizedException(__('At least two product SKUs are required for comparison.'));
        }
        $focus = $this->normalizeFocus($focus);
        $goal = mb_substr(trim($goal), 0, 500);
        $items = [];
        foreach ($skus as $sku) {
            try {
                $items[] = $this->experience->get($sku, $storeId, 1.0, 3, $customerGroupId);
            } catch (\Throwable) {
                // Keep a comparison usable when one stale recent result was removed from the catalog.
            }
        }
        if (count($items) < 2) {
            throw new LocalizedException(__('I could not load enough of those products to compare.'));
        }

        $rows = [];
        if (in_array('price', $focus, true)) {
            $rows[] = $this->row('price', (string)__('Current price'), $items, static fn(array $x): string => (string)($x['price']['formatted_final_price'] ?? ''));
            $rows[] = $this->row('discount', (string)__('Discount'), $items, static fn(array $x): string => (float)($x['price']['discount_percent'] ?? 0) > 0 ? rtrim(rtrim(number_format((float)$x['price']['discount_percent'], 2, '.', ''),'0'),'.') . '%' : (string)__('None'));
        }
        if (in_array('inventory', $focus, true)) {
            $rows[] = $this->row('availability', (string)__('Availability'), $items, static fn(array $x): string => (string)($x['inventory']['message'] ?? $x['inventory']['status'] ?? ''));
        }
        if (in_array('description', $focus, true)) {
            $rows[] = $this->row('description', (string)__('Description'), $items, fn(array $x): string => $this->excerpt((string)($x['description'] ?: $x['short_description']), 750));
        }
        if (in_array('categories', $focus, true)) {
            $rows[] = $this->row('categories', (string)__('Categories'), $items, static fn(array $x): string => implode(', ', array_values(array_filter(array_map(static fn(array $c): string => (string)($c['name'] ?? ''), (array)($x['categories'] ?? []))))));
        }
        if (in_array('reviews', $focus, true)) {
            $rows[] = $this->row('reviews', (string)__('Approved reviews'), $items, function (array $x): string {
                $count=(int)($x['reviews']['total_count']??0);
                $snippets=[];
                foreach(array_slice((array)($x['reviews']['items']??[]),0,2) as $review){
                    $title=trim((string)($review['title']??''));
                    $detail=$this->excerpt((string)($review['detail']??''),220);
                    if($title!==''||$detail!=='')$snippets[]=trim($title . ($title!==''&&$detail!==''?': ':'') . $detail);
                }
                return (string)$count . (empty($snippets)?'':(' — ' . implode(' | ',$snippets)));
            });
        }
        if (in_array('options', $focus, true)) {
            $rows[] = $this->row('options', (string)__('Selectable options'), $items, static function (array $x): string {
                $groups = array_values(array_filter(array_map(static fn(array $g): string => (string)($g['label'] ?? ''), (array)($x['options']['groups'] ?? []))));
                return $groups !== [] ? implode(', ', $groups) : (string)__('None required');
            });
        }
        if (in_array('attributes', $focus, true)) {
            foreach ($this->attributeRows($items) as $row) $rows[] = $row;
        }

        $similarities = [];
        $differences = [];
        foreach ($rows as $row) {
            $values = array_values(array_filter(array_map(static fn(array $v): string => trim((string)($v['value'] ?? '')), (array)$row['values']), static fn(string $v): bool => $v !== ''));
            if (count($values) !== count($items)) continue;
            $normalized = array_unique(array_map(static fn(string $v): string => mb_strtolower(trim($v)), $values));
            if (count($normalized) === 1 && !in_array($row['key'], ['description'], true)) {
                $similarities[] = (string)__('%1: %2', (string)$row['label'], $values[0]);
            } elseif (count($normalized) > 1) {
                $differences[] = (string)$row['label'];
            }
        }
        $descriptionThemes = $this->sharedDescriptionThemes($items);
        if ($descriptionThemes !== []) {
            $similarities[] = (string)__('Descriptions share: %1', implode(', ', $descriptionThemes));
        }

        $goalAssessment = $goal !== '' ? $this->goalAssessment($items, $goal) : [];

        $names = array_map(static fn(array $x): string => (string)($x['product']['name'] ?? $x['product']['sku'] ?? ''), $items);
        $message = (string)__('Compared %1.', implode(' vs ', $names));
        if (in_array('description', $focus, true)) {
            $parts = [];
            foreach ($items as $item) {
                $parts[] = (string)__('%1: %2', (string)$item['product']['name'], $this->excerpt((string)($item['description'] ?: $item['short_description']), 650));
            }
            $message .= ' ' . implode(' ', $parts);
        }
        if ($goal !== '' && $goalAssessment !== []) {
            $ranked = $goalAssessment;
            usort($ranked, static fn(array $a, array $b): int => ((int)$b['score']) <=> ((int)$a['score']));
            $top = $ranked[0] ?? null;
            $second = $ranked[1] ?? null;
            if (is_array($top) && (int)$top['score'] > 0 && (!is_array($second) || (int)$top['score'] > (int)$second['score'])) {
                $message .= ' ' . (string)__('For “%1”, %2 has the strongest explicit match in the current catalog wording. This is an evidence match, not a subjective quality score.', $goal, (string)$top['name']);
            } else {
                $message .= ' ' . (string)__('For “%1”, the current catalog wording does not provide a clear evidence-based winner.', $goal);
            }
        }
        if ($differences !== []) {
            $message .= ' ' . (string)__('Key differing areas: %1.', implode(', ', array_slice($differences, 0, 10)));
        }

        return [
            'focus' => $focus,
            'goal' => $goal,
            'goal_assessment' => $goalAssessment,
            'products' => array_values(array_map(static fn(array $x): array => (array)$x['product'], $items)),
            'rows' => $rows,
            'similarities' => array_slice(array_values(array_unique($similarities)), 0, 12),
            'differences' => array_slice(array_values(array_unique($differences)), 0, 20),
            'assistant_message' => trim($message),
        ];
    }

    private function normalizeFocus(array $focus): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(static fn($v): string => mb_strtolower(trim((string)$v)), $focus), static fn(string $v): bool => in_array($v, self::ALLOWED_FOCUS, true))));
        return $normalized !== [] ? $normalized : self::ALLOWED_FOCUS;
    }

    private function row(string $key, string $label, array $items, callable $reader): array
    {
        $values = [];
        foreach ($items as $item) {
            $values[] = [
                'sku' => (string)($item['product']['sku'] ?? ''),
                'name' => (string)($item['product']['name'] ?? ''),
                'value' => trim((string)$reader($item)),
            ];
        }
        return ['key'=>$key,'label'=>$label,'values'=>$values];
    }

    private function attributeRows(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $sku = (string)($item['product']['sku'] ?? '');
            foreach ((array)($item['product']['custom_attributes'] ?? []) as $attribute) {
                $code = (string)($attribute['code'] ?? '');
                if ($code === '') continue;
                $map[$code]['label'] = (string)($attribute['label'] ?? $code);
                $map[$code]['values'][$sku] = (string)($attribute['value'] ?? '');
            }
        }
        $rows = [];
        foreach (array_slice($map, 0, 30, true) as $code => $attribute) {
            $values = [];
            $hasAny = false;
            foreach ($items as $item) {
                $sku = (string)$item['product']['sku'];
                $value = trim((string)($attribute['values'][$sku] ?? ''));
                $hasAny = $hasAny || $value !== '';
                $values[] = ['sku'=>$sku,'name'=>(string)$item['product']['name'],'value'=>$value !== '' ? $value : (string)__('Not stated')];
            }
            if ($hasAny) $rows[] = ['key'=>'attribute:' . $code,'label'=>(string)$attribute['label'],'values'=>$values];
        }
        return $rows;
    }

    private function sharedDescriptionThemes(array $items): array
    {
        $sets = [];
        foreach ($items as $item) {
            $text = mb_strtolower((string)($item['description'] ?: $item['short_description']));
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
            $tokens = array_values(array_unique(array_filter($tokens, static fn(string $v): bool => mb_strlen($v) >= 5 && !in_array($v, ['about','which','these','their','product','products','using','with','from','this','that','your','more','will','have'], true))));
            $sets[] = array_slice($tokens, 0, 200);
        }
        if ($sets === []) return [];
        $shared = array_shift($sets);
        foreach ($sets as $set) $shared = array_values(array_intersect($shared, $set));
        return array_slice($shared, 0, 6);
    }

    private function goalAssessment(array $items, string $goal): array
    {
        $tokens = $this->goalTokens($goal);
        if ($tokens === []) return [];
        $result = [];
        foreach ($items as $item) {
            $product = (array)($item['product'] ?? []);
            $chunks = [];
            foreach ([(string)($item['short_description'] ?? ''), (string)($item['description'] ?? '')] as $text) {
                if (trim($text) !== '') $chunks[] = $text;
            }
            foreach ((array)($product['custom_attributes'] ?? []) as $attribute) {
                $label = trim((string)($attribute['label'] ?? ''));
                $value = trim((string)($attribute['value'] ?? ''));
                if ($value !== '') $chunks[] = trim($label . ': ' . $value);
            }
            foreach ((array)($item['categories'] ?? []) as $category) {
                $name = trim((string)($category['name'] ?? ''));
                if ($name !== '') $chunks[] = 'Category: ' . $name;
            }
            $score = 0;
            $evidence = [];
            foreach ($chunks as $chunk) {
                $lower = mb_strtolower($chunk);
                $matches = 0;
                foreach ($tokens as $token) {
                    if (str_contains($lower, $token)) {
                        $matches++;
                        $score += mb_strlen($token) >= 7 ? 3 : 2;
                    }
                }
                if ($matches > 0 && count($evidence) < 3) {
                    $evidence[] = $this->excerpt($chunk, 320);
                }
            }
            $result[] = [
                'sku' => (string)($product['sku'] ?? ''),
                'name' => (string)($product['name'] ?? $product['sku'] ?? ''),
                'score' => $score,
                'evidence' => array_values(array_unique($evidence)),
            ];
        }
        return $result;
    }

    private function goalTokens(string $goal): array
    {
        $stop = ['compare','comparison','product','products','item','items','first','second','third','fourth','last','which','what','better','best','good','great','suitable','suited','recommended','recommend','based','description','descriptions','price','stock','inventory','review','reviews','rating','ratings','attributes','features','specifications','specs','option','options','category','categories','with','from','this','that','these','those','for','and','the','your','their','about'];
        $parts = preg_split('/[^\p{L}\p{N}_-]+/u', mb_strtolower($goal)) ?: [];
        $tokens = array_values(array_unique(array_filter($parts, static fn(string $v): bool => mb_strlen($v) >= 3 && !in_array($v, $stop, true))));
        return array_slice($tokens, 0, 12);
    }

    private function excerpt(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') return (string)__('Not stated');
        return mb_substr($text, 0, $limit);
    }
}
