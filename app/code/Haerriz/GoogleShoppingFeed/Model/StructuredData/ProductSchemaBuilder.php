<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Model\StructuredData;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductSchemaBuilder
{
    private const IMAGE_ATTRIBUTE_CODES = ['image', 'small_image', 'thumbnail'];
    private const BRAND_ATTRIBUTE_CODES = ['manufacturer', 'brand'];
    private const GTIN_ATTRIBUTE_CODES = ['gtin', 'gtin14', 'gtin13', 'gtin12', 'gtin8', 'ean', 'ean13', 'upc'];
    private const MPN_ATTRIBUTE_CODES = ['mpn', 'manufacturer_part_number'];

    private StoreManagerInterface $storeManager;

    private PolicySchemaBuilder $policySchemaBuilder;

    public function __construct(
        StoreManagerInterface $storeManager,
        PolicySchemaBuilder $policySchemaBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->policySchemaBuilder = $policySchemaBuilder;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Product $product): array
    {
        $images = $this->getImageUrls($product);
        $price = (float) $product->getFinalPrice();

        // Image and a positive active price are required for merchant listings.
        if ($images === [] || $price <= 0) {
            return [];
        }

        $store = $this->storeManager->getStore();
        $productUrl = (string) $product->getProductUrl();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $productUrl . '#product',
            'name' => trim((string) $product->getName()),
            'image' => $images,
            'offers' => [
                '@type' => 'Offer',
                'url' => $productUrl,
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => strtoupper((string) $store->getCurrentCurrencyCode()),
                'availability' => $product->isAvailable()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => $this->policySchemaBuilder->getOrganizationReference(),
                'shippingDetails' => [
                    '@type' => 'OfferShippingDetails',
                    'hasShippingService' => [
                        '@id' => $this->policySchemaBuilder->getShippingServiceId(),
                    ],
                ],
                'hasMerchantReturnPolicy' => [
                    '@id' => $this->policySchemaBuilder->getReturnPolicyId(),
                ],
            ],
        ];

        $description = $this->normalizeText((string) $product->getData('description'));
        if ($description !== '') {
            $schema['description'] = $description;
        }

        $sku = trim((string) $product->getSku());
        if ($this->isValidSku($sku)) {
            $schema['sku'] = $sku;
        }

        $brand = $this->getBrand($product);
        if ($brand !== '') {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        $gtin = $this->getGtin($product);
        if ($gtin !== '') {
            $schema['gtin' . strlen($gtin)] = $gtin;
        }

        $mpn = $this->getFirstRawValue($product, self::MPN_ATTRIBUTE_CODES);
        if ($mpn !== '') {
            $schema['mpn'] = $mpn;
        }

        $ratingSummary = (float) $product->getData('rating_summary');
        $reviewCount = (int) $product->getData('reviews_count');
        if ($ratingSummary > 0 && $reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($ratingSummary / 20, 1),
                'reviewCount' => $reviewCount,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $schema;
    }

    private function isValidSku(string $sku): bool
    {
        if ($sku === '' || preg_match('/[\p{Z}\s]/u', $sku) === 1) {
            return false;
        }

        return mb_check_encoding($sku, 'UTF-8');
    }

    /**
     * @return string[]
     */
    private function getImageUrls(Product $product): array
    {
        $store = $this->storeManager->getStore();
        $mediaBaseUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
        $images = [];

        foreach (self::IMAGE_ATTRIBUTE_CODES as $attributeCode) {
            $path = trim((string) $product->getData($attributeCode));
            if ($this->isUsableImagePath($path)) {
                $images[] = $mediaBaseUrl . '/catalog/product/' . ltrim($path, '/');
            }
        }

        $gallery = $product->getMediaGalleryImages();
        if ($gallery !== null) {
            foreach ($gallery as $galleryImage) {
                if (!$galleryImage instanceof DataObject || (bool) $galleryImage->getData('disabled')) {
                    continue;
                }

                $url = trim((string) $galleryImage->getData('url'));
                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }

        return array_values(array_unique($images));
    }

    private function isUsableImagePath(string $path): bool
    {
        return $path !== '' && $path !== 'no_selection';
    }

    private function getBrand(Product $product): string
    {
        foreach (self::BRAND_ATTRIBUTE_CODES as $attributeCode) {
            try {
                $attributeText = $product->getAttributeText($attributeCode);
            } catch (\Throwable) {
                $attributeText = null;
            }
            if (is_array($attributeText)) {
                $attributeText = reset($attributeText);
            }

            $brand = trim((string) $attributeText);
            if ($brand !== '') {
                return $brand;
            }

            $rawValue = trim((string) $product->getData($attributeCode));
            if ($rawValue !== '' && !ctype_digit($rawValue)) {
                return $rawValue;
            }
        }

        return '';
    }

    private function getGtin(Product $product): string
    {
        foreach (self::GTIN_ATTRIBUTE_CODES as $attributeCode) {
            $candidate = trim((string) $product->getData($attributeCode));
            if (ctype_digit($candidate) && $this->isValidGtin($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function isValidGtin(string $gtin): bool
    {
        $length = strlen($gtin);
        if (!in_array($length, [8, 12, 13, 14], true)) {
            return false;
        }

        $checkDigit = (int) $gtin[$length - 1];
        $sum = 0;
        $weight = 3;
        for ($index = $length - 2; $index >= 0; --$index) {
            $sum += ((int) $gtin[$index]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }

    /**
     * @param string[] $attributeCodes
     */
    private function getFirstRawValue(Product $product, array $attributeCodes): string
    {
        foreach ($attributeCodes as $attributeCode) {
            $value = trim((string) $product->getData($attributeCode));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim(is_string($text) ? $text : '');
    }
}
