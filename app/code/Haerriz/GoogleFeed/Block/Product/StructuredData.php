<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Block\Product;

use Haerriz\GoogleFeed\Model\AvailabilityResolver;
use Haerriz\GoogleFeed\Model\ShippingWeightFormatter;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class StructuredData extends Template
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var AvailabilityResolver
     */
    private $availabilityResolver;

    /**
     * @var ShippingWeightFormatter
     */
    private $shippingWeightFormatter;

    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param AvailabilityResolver $availabilityResolver
     * @param ShippingWeightFormatter $shippingWeightFormatter
     * @param Json $jsonSerializer
     * @param array<mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        AvailabilityResolver $availabilityResolver,
        ShippingWeightFormatter $shippingWeightFormatter,
        Json $jsonSerializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->availabilityResolver = $availabilityResolver;
        $this->shippingWeightFormatter = $shippingWeightFormatter;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @return string
     */
    public function getJsonLd()
    {
        $product = $this->registry->registry('current_product');

        if (!$product) {
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

        $image = (string) $product->getImage();
        $imageUrl = '';

        if ($image !== '' && $image !== 'no_selection') {
            $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $image;
        }

        $description = strip_tags((string) ($product->getShortDescription() ?: $product->getDescription()));
        $description = trim(preg_replace('/\s+/', ' ', $description));

        if ($description === '') {
            $description = (string) $product->getName();
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $product->getName(),
            'sku' => (string) $product->getSku(),
            'description' => $description,
            'image' => $imageUrl,
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->getProductUrl(),
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
                'unitText' => isset($parts[1]) ? $parts[1] : 'kg',
            ];
        }

        return $this->jsonSerializer->serialize($data);
    }
}
