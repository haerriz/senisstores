<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Model\Feed;

use Haerriz\GoogleFeed\Model\AvailabilityResolver;
use Haerriz\GoogleFeed\Model\ProductIdResolver;
use Haerriz\GoogleFeed\Model\ShippingWeightFormatter;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Generator
{
    private const XML_PATH_ENABLED = 'haerriz_googlefeed/general/enabled';
    private const XML_PATH_TITLE = 'haerriz_googlefeed/general/title';
    private const XML_PATH_DESCRIPTION = 'haerriz_googlefeed/general/description';
    private const XML_PATH_BRAND = 'haerriz_googlefeed/general/default_brand';
    private const XML_PATH_CONDITION = 'haerriz_googlefeed/general/default_condition';

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var AvailabilityResolver
     */
    private $availabilityResolver;

    /**
     * @var ShippingWeightFormatter
     */
    private $shippingWeightFormatter;

    /**
     * @var ProductIdResolver
     */
    private $productIdResolver;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param AvailabilityResolver $availabilityResolver
     * @param ShippingWeightFormatter $shippingWeightFormatter
     * @param ProductIdResolver $productIdResolver
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        AvailabilityResolver $availabilityResolver,
        ShippingWeightFormatter $shippingWeightFormatter,
        ProductIdResolver $productIdResolver
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->availabilityResolver = $availabilityResolver;
        $this->shippingWeightFormatter = $shippingWeightFormatter;
        $this->productIdResolver = $productIdResolver;
    }

    /**
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function generate($storeId = null)
    {
        $store = $this->storeManager->getStore($storeId);
        $storeId = (int) $store->getId();
        $mediaBaseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect([
            'name',
            'description',
            'short_description',
            'sku',
            'price',
            'special_price',
            'image',
            'url_key',
            'weight',
        ]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ],
        ]);
        $collection->addPriceData();
        $collection->addUrlRewrite();

        $title = (string) $this->scopeConfig->getValue(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE, $storeId);
        $description = (string) $this->scopeConfig->getValue(
            self::XML_PATH_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $defaultBrand = (string) $this->scopeConfig->getValue(self::XML_PATH_BRAND, ScopeInterface::SCOPE_STORE, $storeId);
        $defaultCondition = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CONDITION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', $title);
        $xml->writeElement('link', $store->getBaseUrl());
        $xml->writeElement('description', $description);

        /** @var Product $product */
        foreach ($collection as $product) {
            $this->writeProductItem($xml, $product, $store, $mediaBaseUrl, $defaultBrand, $defaultCondition);
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @param \XMLWriter $xml
     * @param Product $product
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @param string $mediaBaseUrl
     * @param string $defaultBrand
     * @param string $defaultCondition
     * @return void
     */
    private function writeProductItem(
        \XMLWriter $xml,
        Product $product,
        \Magento\Store\Api\Data\StoreInterface $store,
        $mediaBaseUrl,
        $defaultBrand,
        $defaultCondition
    ) {
        $productUrl = $product->getProductUrl();
        $image = (string) $product->getImage();

        if ($productUrl === '' || $image === '' || $image === 'no_selection') {
            return;
        }

        $weight = $product->getWeight();
        $shippingWeight = $this->shippingWeightFormatter->format(
            $weight !== null ? (float) $weight : null,
            (int) $store->getId()
        );

        if ($shippingWeight === null) {
            return;
        }

        $availability = $this->availabilityResolver->resolve($product);
        $price = $product->getFinalPrice();
        $description = strip_tags((string) ($product->getShortDescription() ?: $product->getDescription()));
        $description = trim(preg_replace('/\s+/', ' ', $description));

        if ($description === '') {
            $description = (string) $product->getName();
        }

        $brand = $defaultBrand;

        $xml->startElement('item');
        $xml->writeElement('g:id', $this->productIdResolver->resolveId($product));
        $xml->writeElement('g:title', $this->truncate((string) $product->getName(), 150));
        $xml->writeElement('g:description', $this->truncate($description, 5000));
        $xml->writeElement('g:link', $productUrl);
        $xml->writeElement('g:image_link', $mediaBaseUrl . 'catalog/product' . $image);
        $xml->writeElement('g:condition', $defaultCondition);
        $xml->writeElement('g:availability', $availability);
        $xml->writeElement(
            'g:price',
            number_format((float) $price, 2, '.', '') . ' ' . $store->getCurrentCurrencyCode()
        );
        $xml->writeElement('g:brand', $brand);
        $xml->writeElement('g:shipping_weight', $shippingWeight);
        $xml->writeElement('g:identifier_exists', 'no');
        $xml->writeElement('g:mpn', $this->productIdResolver->resolveMpn($product));
        $xml->endElement();
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string $value
     * @param int $limit
     * @return string
     */
    private function truncate($value, $limit)
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit - 3) . '...';
    }
}
