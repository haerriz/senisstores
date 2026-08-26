<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Haerriz\AgenticCommerce\Model\ProductPresenter;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shopper-safe product content/intelligence projection.
 *
 * It deliberately returns rendered storefront product content and approved display attributes,
 * never raw EAV rows or arbitrary database columns. HTML/Page Builder markup is converted to
 * bounded plain text before it is returned to chat, GraphQL, REST or an external response composer.
 */
class ProductContentService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductPresenter $presenter,
        private StoreManagerInterface $stores,
        private Config $config
    ) {}

    public function get(string $sku, int $storeId, ?int $customerGroupId = null): array
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
        $short = $this->plainText((string)$product->getData('short_description'), 1800);
        $description = $this->plainText((string)$product->getData('description'), 6500);
        $highlights = $this->highlights((string)$product->getData('description'), $short);
        $specifications = [];
        foreach (array_slice((array)($presented['custom_attributes'] ?? []), 0, $this->config->getMaxProductSpecifications($storeId)) as $attribute) {
            if (!is_array($attribute) || trim((string)($attribute['value'] ?? '')) === '') {
                continue;
            }
            $specifications[] = [
                'code' => (string)($attribute['code'] ?? ''),
                'label' => (string)($attribute['label'] ?? $attribute['code'] ?? ''),
                'value' => (string)$attribute['value'],
            ];
        }

        $summarySource = $description !== '' ? $description : $short;
        $summary = $summarySource !== ''
            ? mb_substr($summarySource, 0, 1200)
            : (string)__('No storefront product description is currently configured for this product.');

        return [
            'product' => $presented,
            'short_description' => $short,
            'description' => $description,
            'highlights' => $highlights,
            'specifications' => $specifications,
            'media_gallery' => $this->mediaGallery($product, $storeId),
            'meta_title' => $this->plainText((string)$product->getData('meta_title'), 255),
            'meta_description' => $this->plainText((string)$product->getData('meta_description'), 1000),
            'assistant_message' => (string)__('%1 — %2', (string)$product->getName(), $summary),
        ];
    }

    private function mediaGallery(\Magento\Catalog\Api\Data\ProductInterface $product, int $storeId): array
    {
        $items=[];
        try {
            $base=rtrim((string)$this->stores->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),'/');
            foreach(array_slice((array)($product->getMediaGalleryEntries()??[]),0,$this->config->getMaxProductMedia($storeId)) as $entry){
                if(!is_object($entry) || (bool)$entry->getDisabled())continue;
                $file=trim((string)$entry->getFile());
                if($file==='')continue;
                $items[]=[
                    'url'=>$base . '/catalog/product/' . ltrim($file,'/'),
                    'label'=>(string)($entry->getLabel()??''),
                    'position'=>(int)($entry->getPosition()??0),
                ];
                if(count($items)>=12)break;
            }
        } catch (\Throwable) {
            return [];
        }
        usort($items,static fn(array $a,array $b):int=>$a['position']<=>$b['position']);
        return $items;
    }

    private function highlights(string $html, string $short): array
    {
        $items = [];
        if ($html !== '') {
            if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/isu', $html, $matches)) {
                foreach ((array)($matches[1] ?? []) as $item) {
                    $plain = $this->plainText((string)$item, 350);
                    if ($plain !== '') {
                        $items[] = $plain;
                    }
                }
            }
        }
        if ($items === [] && $short !== '') {
            foreach ($this->sentences($short) as $sentence) {
                if (mb_strlen($sentence) >= 18) {
                    $items[] = mb_substr($sentence, 0, 350);
                }
                if (count($items) >= 6) break;
            }
        }
        return array_slice(array_values(array_unique($items)), 0, 12);
    }

    /** @return string[] */
    public function sentences(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') return [];
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\p{N}])/u', $text) ?: [$text];
        return array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
    }

    public function plainText(string $value, int $limit): string
    {
        if ($value === '') return '';
        // Remove executable/non-content blocks before strip_tags so script/style bodies are not
        // echoed back into product chat as visible pseudo-content. Page Builder/storefront HTML is
        // then converted into bounded shopper-readable text.
        $value = preg_replace('/<\s*(script|style|template|noscript)\b[^>]*>.*?<\/\s*\1\s*>/isu', ' ', $value) ?? $value;
        $value = preg_replace('/<\s*br\s*\/?\s*>/iu', '. ', $value) ?? $value;
        $value = preg_replace('/<\/(?:p|div|li|h[1-6])\s*>/iu', '. ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = preg_replace('/\.{2,}/u', '.', $value) ?? $value;
        return mb_substr($value, 0, max(1, $limit));
    }
}
