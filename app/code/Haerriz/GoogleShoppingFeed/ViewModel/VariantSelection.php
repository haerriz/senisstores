<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

final class VariantSelection implements ArgumentInterface
{
    /**
     * Return the native widget JSON and the data for its exact selected child.
     */
    public function configure(string $json, array $query): array
    {
        $unchanged = ['json' => $json, 'selection' => null];
        if ($query === []) {
            return $unchanged;
        }

        $config = json_decode($json, true);
        if (!is_array($config) || empty($config['attributes']) || empty($config['index'])) {
            return $unchanged;
        }
        if (!empty($config['inputsInitialized']) || !empty($config['defaultValues'])) {
            return $unchanged;
        }

        $defaults = [];
        $variantQuery = [];
        $labels = [];
        $eligibleChildren = null;
        foreach ($config['attributes'] as $attributeId => $attribute) {
            $attributeCode = $attribute['code'];
            $optionId = $query[$attributeCode] ?? null;
            if (!is_string($optionId) || preg_match('/\A[1-9][0-9]{0,9}\z/', $optionId) !== 1) {
                return $unchanged;
            }

            $option = $this->getOption($attribute, $optionId);
            if ($option === null) {
                return $unchanged;
            }

            $availableChildren = $option['products'];
            if (!empty($config['canDisplayShowOutOfStockStatus'])) {
                $availableChildren = array_intersect(
                    $availableChildren,
                    $config['salable'][$attributeId][$optionId] ?? []
                );
            }
            $eligibleChildren = $eligibleChildren === null
                ? $availableChildren
                : array_intersect($eligibleChildren, $availableChildren);
            if ($eligibleChildren === []) {
                return $unchanged;
            }

            $defaults[$attributeId] = $optionId;
            $variantQuery[$attributeCode] = $optionId;
            $label = trim((string) ($attribute['label'] ?? ''));
            $optionLabel = trim((string) ($option['label'] ?? ''));
            if ($label !== '' && $optionLabel !== '') {
                $labels[] = $label . ': ' . $optionLabel;
            }
        }

        $matchedChildren = [];
        foreach ($eligibleChildren as $childId) {
            $childOptions = $config['index'][$childId] ?? null;
            if (is_array($childOptions) && $childOptions == $defaults
                && isset($config['optionPrices'][$childId]['finalPrice']['amount'])) {
                $matchedChildren[(int) $childId] = true;
            }
        }
        if (count($matchedChildren) !== 1) {
            return $unchanged;
        }

        $payload = json_decode($json);
        $payload->defaultValues = (object) $defaults;
        $encoded = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
        );
        if ($encoded === false) {
            return $unchanged;
        }

        $childId = array_key_first($matchedChildren);
        return [
            'json' => $encoded,
            'selection' => [
                'child_id' => $childId,
                'query' => $variantQuery,
                'labels' => $labels,
                'price' => (float) $config['optionPrices'][$childId]['finalPrice']['amount'],
                'images' => $this->getImageUrls($config['images'][$childId] ?? []),
            ],
        ];
    }

    private function getOption(array $attribute, string $optionId): ?array
    {
        foreach ($attribute['options'] as $option) {
            if ((string) $option['id'] === $optionId) {
                return $option;
            }
        }

        return null;
    }

    private function getImageUrls(array $gallery): array
    {
        $primary = [];
        $secondary = [];
        foreach ($gallery as $item) {
            $url = (string) ($item['full'] ?? '');
            if (($item['type'] ?? '') !== 'image'
                || !filter_var($url, FILTER_VALIDATE_URL)
                || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                continue;
            }
            if (!empty($item['isMain'])) {
                $primary[] = $url;
            } else {
                $secondary[] = $url;
            }
        }

        return array_values(array_unique(array_merge($primary, $secondary)));
    }
}
