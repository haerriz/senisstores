<?php
namespace Haerriz\GoogleShoppingFeed\Block\Seo;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;

class Schema extends Template
{
    protected $registry;
    protected $storeManager;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->storeManager = $context->getStoreManager();
        parent::__construct($context, $data);
    }

    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    public function getJsonLd()
    {
        $product = $this->getProduct();
        
        if ($product) {
            return $this->getProductSchema($product);
        }

        // If not product page, maybe we are on home page
        if ($this->getRequest()->getFullActionName() == 'cms_index_index') {
            return $this->getOrganizationSchema();
        }

        return '';
    }

    protected function getProductSchema(Product $product)
    {
        $store = $this->storeManager->getStore();
        $currency = $store->getCurrentCurrencyCode();
        
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->getName(),
            'image' => [
                $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage()
            ],
            'description' => strip_tags($product->getDescription()),
            'sku' => $product->getSku(),
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->getProductUrl(),
                'priceCurrency' => $currency,
                'price' => number_format($product->getFinalPrice(), 2, '.', ''),
                'availability' => $product->isAvailable() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
            ]
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function getOrganizationSchema()
    {
        $store = $this->storeManager->getStore();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => "Seni's Stores - Theni Hardware",
            'url' => $store->getBaseUrl(),
            'logo' => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'logo/default/logo.png',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+91-XXXX-XXXXXX',
                'contactType' => 'customer service'
            ]
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
