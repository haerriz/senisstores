<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\OfferIdentityResolverInterface;
use Haerriz\GoogleShoppingFeed\Model\CategoryIdResolver;
use Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ProductValueResolver implements \Haerriz\GoogleShoppingFeed\Api\ProductValueResolverInterface
{
    private $imageHelper;
    private $storeManager;
    private $offerIdentityResolver;
    private $utmBuilder;
    private $categoryIdResolver;
    private $logger;

    public function __construct(
        ImageHelper $imageHelper,
        StoreManagerInterface $storeManager,
        OfferIdentityResolverInterface $offerIdentityResolver,
        UtmBuilder $utmBuilder,
        CategoryIdResolver $categoryIdResolver,
        LoggerInterface $logger
    ) {
        $this->imageHelper           = $imageHelper;
        $this->storeManager          = $storeManager;
        $this->offerIdentityResolver = $offerIdentityResolver;
        $this->utmBuilder            = $utmBuilder;
        $this->categoryIdResolver    = $categoryIdResolver;
        $this->logger                = $logger;
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

            // FIX 2: OfferIdentityResolver::resolve() — used for offer ID / SKU
            case 'sku':
            case 'offer_id':
                try {
                    return $this->offerIdentityResolver->resolve($product);
                } catch (\Exception $e) {
                    $this->logger->debug("OfferIdentityResolver failed for [{$product->getId()}]: " . $e->getMessage());
                    return (string)$product->getSku();
                }

            // FIX 3: UtmBuilder::build() — used for product URLs with UTM tracking
            case 'product_url':
                try {
                    $url = $product->setStoreId((int)$profile->getStoreId())->getProductUrl();
                    return $this->utmBuilder->build($url, $profile);
                } catch (\Exception $e) {
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

            // FIX 4: CategoryIdResolver::resolve() — resolve google_product_category from categories
            case 'google_product_category':
                try {
                    return $this->categoryIdResolver->resolve($product);
                } catch (\Exception $e) {
                    $this->logger->debug("CategoryIdResolver failed for [{$product->getSku()}]: " . $e->getMessage());
                    return '';
                }

            case 'quantity_and_stock_status':
            case 'availability':
                try {
                    $stockItem = $product->getExtensionAttributes()
                        ? $product->getExtensionAttributes()->getStockItem()
                        : null;
                    if ($stockItem && $stockItem->getIsInStock()) {
                        return 'in stock';
                    }
                } catch (\Exception $e) {}
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
                $specialPrice = (float)$product->getSpecialPrice();
                if ($specialPrice > 0) {
                    $from  = $product->getSpecialFromDate();
                    $to    = $product->getSpecialToDate();
                    $now   = date('Y-m-d');
                    $valid = (!$from || $now >= substr($from, 0, 10))
                          && (!$to   || $now <= substr($to, 0, 10));
                    if ($valid) {
                        return number_format($specialPrice, 2, '.', '') . ' INR';
                    }
                }
                return number_format((float)$product->getPrice(), 2, '.', '') . ' INR';

            case 'currency':
                try {
                    return $this->storeManager->getStore((int)$profile->getStoreId())->getCurrentCurrencyCode();
                } catch (\Exception $e) {
                    return 'INR';
                }

            default:
                $value = $product->getData($attributeCode);
                if ($value === null && method_exists($product, 'get' . str_replace('_', '', ucwords($attributeCode, '_')))) {
                    $getter = 'get' . str_replace('_', '', ucwords($attributeCode, '_'));
                    $value  = $product->$getter();
                }
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return (string)($value ?? '');
        }
    }
}
