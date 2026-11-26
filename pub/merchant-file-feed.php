<?php

declare(strict_types=1);

use Haerriz\GoogleFeed\Model\ProductIdResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

require dirname(__DIR__) . '/app/bootstrap.php';

// Keep the IDs already associated with these offers in Merchant Center.
$existingOfferIds = [
    'Crowbar - கடப்பாரை - kadapaarai - 5.5 feet' => 'Crowbar-kadapaarai-5-5-feet',
    'Crowbar - கடப்பாரை - kadapaarai - 4.5 feet' => 'Crowbar-kadapaarai-4-5-feet',
];

// The product packaging/landing pages identify these marketed brands.
$verifiedBrandsByUrlKey = [
    'birla-white-portland-cement-5kg' => 'Birla White',
    'birla-white-portland-cement-1kg' => 'Birla White',
    'm-seal-regular-90g' => 'M-Seal',
    'm-seal-phataphat-25g' => 'M-Seal',
    'ezy-seal' => 'M-Seal',
];

function feedText(string $value, int $maxLength): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $maxLength, 'UTF-8');
}

function feedImageUrl(Product $product, string $imageRoot, string $mediaUrl): ?string
{
    $relative = ltrim((string)$product->getImage(), '/');
    if ($relative === '' || $relative === 'no_selection') {
        return null;
    }

    $path = realpath($imageRoot . DIRECTORY_SEPARATOR . $relative);
    if ($path === false || !str_starts_with($path, $imageRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
        return null;
    }

    $image = getimagesize($path);
    if (!$image || $image[0] < 500 || $image[1] < 500
        || !in_array($image['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
    ) {
        return null;
    }

    return $mediaUrl . '/catalog/product/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
}

try {
    $objects = Bootstrap::create(BP, $_SERVER)->getObjectManager();
    $objects->get(State::class)->setAreaCode('frontend');
    $stores = $objects->get(StoreManagerInterface::class);
    $store = $stores->getDefaultStoreView();
    if (!$store || $store->getBaseCurrencyCode() !== 'INR') {
        throw new RuntimeException('Unavailable India feed store');
    }
    $storeId = (int)$store->getId();
    $stores->setCurrentStore($storeId);

    $imageRoot = realpath(__DIR__ . '/media/catalog/product');
    if ($imageRoot === false) {
        throw new RuntimeException('Unavailable product media');
    }
    $mediaUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true), '/');
    $offerIdResolver = $objects->get(ProductIdResolver::class);
    $products = $objects->get(CollectionFactory::class)->create();
    $products->setStoreId($storeId)
        ->addStoreFilter($storeId)
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Status::STATUS_ENABLED)
        ->addAttributeToFilter(
            'visibility',
            ['in' => $objects->get(Visibility::class)->getVisibleInSiteIds()]
        )
        ->addAttributeToSelect([
            'name', 'sku', 'url_key', 'image', 'price', 'special_price', 'special_from_date',
            'special_to_date', 'short_description', 'description', 'weight', 'manufacturer',
        ])
        ->setOrder('entity_id', 'ASC');

    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('rss');
    $xml->writeAttribute('version', '2.0');
    $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
    $xml->startElement('channel');
    $xml->writeElement('title', 'Seni S Stores');
    $xml->writeElement('link', 'https://senisstores.com/');
    $xml->writeElement('description', 'Seni S Stores scheduled product feed for India');

    $offerIds = [];
    foreach ($products as $product) {
        $imageUrl = feedImageUrl($product, $imageRoot, $mediaUrl);
        $title = feedText((string)$product->getName(), 150);
        $description = feedText((string)$product->getShortDescription(), 5000);
        if ($description === '') {
            $description = feedText((string)$product->getDescription(), 5000);
        }
        $price = (float)$product->getFinalPrice();
        $weight = (float)$product->getWeight();
        if ($imageUrl === null || $title === '' || $description === '' || $price <= 0 || $weight <= 0) {
            continue;
        }

        $link = (string)$product->getProductUrl();
        if (!str_starts_with($link, 'https://senisstores.com/')) {
            continue;
        }
        $approvedId = $existingOfferIds[(string)$product->getSku()] ?? null;
        $offerId = $approvedId ?? $offerIdResolver->resolveId($product);
        $identity = strtolower($offerId);
        if (isset($offerIds[$identity])) {
            throw new RuntimeException('Duplicate Merchant product ID');
        }
        $offerIds[$identity] = true;
        $fields = [
            'id' => $offerId,
            'title' => $title,
            'description' => $description,
            'link' => $link,
            'image_link' => $imageUrl,
            'availability' => $product->isSalable() ? 'in_stock' : 'out_of_stock',
            'price' => sprintf('%.2f INR', $price),
            'condition' => 'new',
            'shipping_weight' => sprintf('%.3f kg', $weight),
        ];
        $brand = $product->getAttributeText('manufacturer');
        if (is_string($brand) && trim($brand) !== '') {
            $fields['brand'] = feedText($brand, 70);
        } elseif (isset($verifiedBrandsByUrlKey[(string)$product->getUrlKey()])) {
            $fields['brand'] = $verifiedBrandsByUrlKey[(string)$product->getUrlKey()];
        }
        if ($approvedId !== null && !isset($fields['brand'])) {
            $fields['identifier_exists'] = 'no';
        }

        $xml->startElement('item');
        foreach ($fields as $field => $value) {
            $xml->writeElement('g:' . $field, $value);
        }
        $xml->endElement();
    }
    if (!$offerIds) {
        throw new RuntimeException('No products available for the India feed');
    }
    $xml->endElement();
    $xml->endElement();
    $xml->endDocument();

    header('Content-Type: application/rss+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    echo $xml->outputMemory();
} catch (Throwable $error) {
    error_log('Seni product feed failed: ' . get_class($error));
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Feed temporarily unavailable.';
}
