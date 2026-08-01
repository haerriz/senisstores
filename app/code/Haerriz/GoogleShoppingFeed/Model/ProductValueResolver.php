<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\OfferIdentityResolverInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductValueResolverInterface;
use Haerriz\GoogleShoppingFeed\Model\Taxonomy\Mapping as TaxonomyMapping;
use Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ProductValueResolver implements ProductValueResolverInterface
{
    private ImageHelper $imageHelper;
    private StoreManagerInterface $storeManager;
    private OfferIdentityResolverInterface $offerIdentityResolver;
    private UtmBuilder $utmBuilder;
    private TaxonomyMapping $taxonomyMapping;
    private CatalogHelper $catalogHelper;
    private ProfileConfigReader $configReader;
    private LoggerInterface $logger;

    public function __construct(
        ImageHelper $imageHelper,
        StoreManagerInterface $storeManager,
        OfferIdentityResolverInterface $offerIdentityResolver,
        UtmBuilder $utmBuilder,
        TaxonomyMapping $taxonomyMapping,
        CatalogHelper $catalogHelper,
        ProfileConfigReader $configReader,
        LoggerInterface $logger
    ) {
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->offerIdentityResolver = $offerIdentityResolver;
        $this->utmBuilder = $utmBuilder;
        $this->taxonomyMapping = $taxonomyMapping;
        $this->catalogHelper = $catalogHelper;
        $this->configReader = $configReader;
        $this->logger = $logger;
    }

    public function resolve(array $mapping, Product $product, FeedProfileInterface $profile)
    {
        $sourceType = $mapping['source_type'] ?? 'attribute';
        if ($sourceType === 'static') {
            return $mapping['static_value'] ?? '';
        }

        $attributeCode = $mapping['magento_attribute'] ?? $mapping['attribute'] ?? '';
        if (!$attributeCode) {
            return '';
        }

        switch ($attributeCode) {
            case 'sku':
            case 'offer_id':
                try {
                    return $this->offerIdentityResolver->resolve($product);
                } catch (\Exception $e) {
                    $this->logger->debug("OfferIdentityResolver failed for [{$product->getId()}]: " . $e->getMessage());
                    return (string)$product->getSku();
                }

            case 'product_url':
                try {
                    $url = $product->setStoreId((int)$profile->getStoreId())->getProductUrl();
                    return $this->utmBuilder->buildUrl($url, $profile, $product);
                } catch (\Throwable $e) {
                    $this->logger->debug("UtmBuilder failed for [{$product->getSku()}]: " . $e->getMessage());
                    return $product->getProductUrl();
                }

            case 'image_url':
                try {
                    return $this->imageHelper
                        ->init($product, 'product_page_image_large')
                        ->setImageFile($product->getImage())
                        ->getUrl();
                } catch (\Exception $e) {
                    $this->logger->debug("ImageHelper failed for [{$product->getSku()}]: " . $e->getMessage());
                    return '';
                }

            case 'google_product_category':
                try {
                    $direct = trim((string)$product->getData('google_product_category'));
                    if ($direct !== '') {
                        return $direct;
                    }
                    $categoryIds = array_map('intval', (array)$product->getCategoryIds());
                    $categoryId = $categoryIds[0] ?? 0;
                    if ($categoryId <= 0) {
                        return '';
                    }
                    return $this->taxonomyMapping->resolveCategoryPath($categoryId);
                } catch (\Throwable $e) {
                    $this->logger->debug("Taxonomy mapping failed for [{$product->getSku()}]: " . $e->getMessage());
                    return '';
                }

            case 'item_group_id':
                return (string)($product->getData('item_group_id')
                    ?: $product->getData('parent_sku')
                    ?: '');

            case 'color':
                return $this->resolveOptionAttribute($product, 'color');

            case 'size':
                return $this->resolveOptionAttribute($product, 'size');

            case 'quantity_and_stock_status':
            case 'availability':
                try {
                    $stockItem = $product->getExtensionAttributes()
                        ? $product->getExtensionAttributes()->getStockItem()
                        : null;
                    if ($stockItem && $stockItem->getIsInStock()) {
                        return 'in stock';
                    }
                } catch (\Exception $e) {
                    // fall through
                }
                return 'out of stock';

            case 'quantity':
                try {
                    $stockItem = $product->getExtensionAttributes()
                        ? $product->getExtensionAttributes()->getStockItem()
                        : null;
                    return $stockItem ? max(0, (int)$stockItem->getQty()) : 0;
                } catch (\Exception $e) {
                    return 0;
                }

            case 'price':
                return $this->resolvePrice($product, $profile);

            case 'currency':
                return $this->resolveCurrency($product, $profile);

            default:
                $value = $product->getData($attributeCode);
                if ($value === null) {
                    $getter = 'get' . str_replace('_', '', ucwords($attributeCode, '_'));
                    if (method_exists($product, $getter)) {
                        $value = $product->$getter();
                    }
                }
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return (string)($value ?? '');
        }
    }

    private function resolvePrice(Product $product, FeedProfileInterface $profile): string
    {
        $currency = $this->resolveCurrency($product, $profile);
        $price = $this->getBasePrice($product);

        $includeTax = $this->shouldIncludeTax($profile);
        if ($includeTax) {
            try {
                $store = $this->storeManager->getStore((int)($profile->getStoreId() ?: $product->getStoreId()));
                $price = (float)$this->catalogHelper->getTaxPrice($product, $price, true, null, null, null, $store, null, true);
            } catch (\Throwable $e) {
                $this->logger->debug('Tax price resolve failed: ' . $e->getMessage());
            }
        }

        return number_format($price, 2, '.', '') . ' ' . $currency;
    }

    private function getBasePrice(Product $product): float
    {
        $specialPrice = (float)$product->getSpecialPrice();
        if ($specialPrice > 0) {
            $from = $product->getSpecialFromDate();
            $to = $product->getSpecialToDate();
            $now = date('Y-m-d');
            $valid = (!$from || $now >= substr((string)$from, 0, 10))
                && (!$to || $now <= substr((string)$to, 0, 10));
            if ($valid) {
                return $specialPrice;
            }
        }

        return (float)$product->getFinalPrice();
    }

    private function shouldIncludeTax(FeedProfileInterface $profile): bool
    {
        if ($profile->getData('price_includes_tax') !== null && $profile->getData('price_includes_tax') !== '') {
            return (bool)$profile->getData('price_includes_tax');
        }
        if ($profile->getData('include_tax') !== null && $profile->getData('include_tax') !== '') {
            return (bool)$profile->getData('include_tax');
        }

        return $this->configReader->getBoolean($profile, 'include_tax', false)
            || $this->configReader->getBoolean($profile, 'price_includes_tax', false);
    }

    private function resolveOptionAttribute(Product $product, string $code): string
    {
        $value = $product->getData($code);
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $text = $product->getAttributeText($code);
            if (is_array($text)) {
                return implode(', ', $text);
            }
            if ($text !== false && $text !== null && $text !== '') {
                return (string)$text;
            }
        } catch (\Throwable $e) {
            // fall through to raw value
        }

        return is_array($value) ? implode(', ', $value) : (string)$value;
    }

    private function resolveCurrency(Product $product, FeedProfileInterface $profile): string
    {
        $currency = trim((string)$profile->getCurrency());
        if ($currency !== '') {
            return strtoupper($currency);
        }

        try {
            $storeId = (int)($profile->getStoreId() ?: $product->getStoreId());
            return (string)$this->storeManager->getStore($storeId)->getCurrentCurrencyCode();
        } catch (\Exception $e) {
            $this->logger->debug('Currency resolve failed: ' . $e->getMessage());
            return 'USD';
        }
    }
}
