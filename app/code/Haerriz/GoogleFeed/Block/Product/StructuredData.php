<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Block\Product;

use Haerriz\GoogleFeed\Model\AvailabilityResolver;
use Haerriz\GoogleFeed\Model\ProductReviewSchemaProvider;
use Haerriz\GoogleFeed\Model\ShippingWeightFormatter;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class StructuredData extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param AvailabilityResolver $availabilityResolver
     * @param ShippingWeightFormatter $shippingWeightFormatter
     * @param ProductReviewSchemaProvider $productReviewSchemaProvider
     * @param Json $jsonSerializer
     * @param array<mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly AvailabilityResolver $availabilityResolver,
        private readonly ShippingWeightFormatter $shippingWeightFormatter,
        private readonly ProductReviewSchemaProvider $productReviewSchemaProvider,
        private readonly Json $jsonSerializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return string
     */
    public function getProductImageUrl(): string
    {
        $product = $this->getProduct();

        if (!$product) {
            return '';
        }

        $image = (string) $product->getImage();

        if ($image === '' || $image === 'no_selection') {
            return '';
        }

        $store = $this->_storeManager->getStore();

        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $image;
    }

    /**
     * @return string
     */
    public function getJsonLd(): string
    {
        $product = $this->getProduct();

        if (!$product) {
            return '';
        }

        $imageUrl = $this->getProductImageUrl();

        if ($imageUrl === '') {
            return '';
        }

        $store = $this->_storeManager->getStore();
        $availability = $this->availabilityResolver->resolve($product);
        $availabilitySchema = $availability === 'in stock'
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $weight = $product->getWeight();
        $shippingWeight = $this->shippingWeightFormatter->format(
            $weight !== null ? (float) $weight : null,
            (int) $store->getId()
        );

        $description = strip_tags((string) ($product->getShortDescription() ?: $product->getDescription()));
        $description = trim(preg_replace('/\s+/', ' ', $description));

        if ($description === '') {
            $description = (string) $product->getName();
        }

        $productUrl = (string) $product->getProductUrl();

        $data = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            '@id' => $productUrl . '#product',
            'url' => $productUrl,
            'name' => (string) $product->getName(),
            'sku' => (string) $product->getSku(),
            'description' => $description,
            'image' => $imageUrl,
            'brand' => [
                '@type' => 'Brand',
                'name' => (string) $store->getFrontendName(),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $productUrl,
                'priceCurrency' => $store->getCurrentCurrencyCode(),
                'price' => number_format((float) $product->getFinalPrice(), 2, '.', ''),
                'availability' => $availabilitySchema,
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        if ($shippingWeight !== null) {
            $parts = explode(' ', $shippingWeight, 2);
            $data['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $parts[0],
                'unitText' => $parts[1] ?? 'kg',
            ];
        }

        $reviewSchema = $this->productReviewSchemaProvider->getSchema($product);
        if ($reviewSchema !== []) {
            $data = array_merge($data, $reviewSchema);
        }

        return $this->jsonSerializer->serialize($data);
    }
}
