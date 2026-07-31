<?php
namespace Haerriz\GoogleShoppingFeed\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class StructuredData extends Template
{
    private $registry;
    private $storeManager;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry     = $registry;
        $this->storeManager = $storeManager;
    }

    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    public function getJsonLd(): string
    {
        $product = $this->getProduct();
        if (!$product) {
            return '';
        }

        try {
            $store    = $this->storeManager->getStore();
            $currency = $store->getCurrentCurrencyCode();
            $mediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

            $imageUrl = '';
            if ($product->getImage() && $product->getImage() !== 'no_selection') {
                $imageUrl = rtrim($mediaUrl, '/') . '/catalog/product' . $product->getImage();
            }

            $schema = [
                '@context' => 'https://schema.org/',
                '@type'    => 'Product',
                'name'     => (string)$product->getName(),
                'sku'      => (string)$product->getSku(),
                'description' => strip_tags((string)$product->getDescription()),
                'brand'    => [
                    '@type' => 'Brand',
                    'name'  => (string)($product->getData('manufacturer') ?: $store->getName()),
                ],
                'offers'   => [
                    '@type'         => 'Offer',
                    'priceCurrency' => $currency,
                    'price'         => number_format((float)$product->getFinalPrice(), 2, '.', ''),
                    'availability'  => $product->isAvailable()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url'           => $product->getProductUrl(),
                    'seller'        => ['@type' => 'Organization', 'name' => $store->getName()],
                ],
            ];

            if ($imageUrl) {
                $schema['image'] = $imageUrl;
            }

            // Add AggregateRating only if review data available
            if ($product->getData('rating_summary')) {
                $schema['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => round((float)$product->getData('rating_summary') / 20, 1),
                    'reviewCount' => (int)$product->getData('reviews_count'),
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ];
            }

        } catch (\Exception $e) {
            return '';
        }

        return json_encode(
            $schema,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
