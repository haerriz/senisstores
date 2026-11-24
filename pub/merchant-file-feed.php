<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

require dirname(__DIR__) . '/app/bootstrap.php';

// Preserve the offer IDs already approved under the scheduled file source.
$offers = [
    'Crowbar-kadapaarai-5-5-feet' => 'Crowbar - கடப்பாரை - kadapaarai - 5.5 feet',
    'Crowbar-kadapaarai-4-5-feet' => 'Crowbar - கடப்பாரை - kadapaarai - 4.5 feet',
];

try {
    $objects = Bootstrap::create(BP, $_SERVER)->getObjectManager();
    $objects->get(State::class)->setAreaCode('frontend');
    $stores = $objects->get(StoreManagerInterface::class);
    $store = $stores->getDefaultStoreView();
    $stores->setCurrentStore($store->getId());
    $products = $objects->get(ProductRepositoryInterface::class);
    $namespace = 'http://base.google.com/ns/1.0';
    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('rss');
    $xml->writeAttribute('version', '2.0');
    $xml->writeAttribute('xmlns:g', $namespace);
    $xml->startElement('channel');
    $xml->writeElement('title', 'Seni S Stores');
    $xml->writeElement('link', 'https://senisstores.com/');
    $xml->writeElement('description', 'Seni S Stores scheduled product feed for India');
    foreach ($offers as $offerId => $sku) {
        $product = $products->get($sku, false, (int) $store->getId());
        if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
            continue;
        }
        $image = (string) $product->getImage();
        $description = trim(html_entity_decode(strip_tags((string) (
            $product->getShortDescription() ?: $product->getDescription()
        )), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $price = (float) $product->getPrice();
        $final = (float) $product->getFinalPrice();
        if ($image === '' || $image === 'no_selection' || $description === '' || $price <= 0 || $final <= 0) {
            throw new RuntimeException('Incomplete product data');
        }
        $fields = [
            'id' => $offerId,
            'title' => (string) $product->getName(),
            'description' => $description,
            'link' => (string) $product->getProductUrl(),
            'image_link' => rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true), '/')
                . '/catalog/product/' . ltrim($image, '/'),
            'availability' => $product->isSalable() ? 'in_stock' : 'out_of_stock',
            'price' => sprintf('%.2f INR', $price),
            'condition' => 'new',
            'identifier_exists' => 'no',
            'shipping_weight' => sprintf('%.3f kg', (float) $product->getWeight()),
        ];
        if ($final < $price) {
            $fields['sale_price'] = sprintf('%.2f INR', $final);
        }
        $xml->startElement('item');
        foreach ($fields as $field => $value) {
            $xml->writeElement('g:' . $field, $value);
        }
        $xml->endElement();
    }
    $xml->endElement();
    $xml->endElement();
    $xml->endDocument();
    header('Content-Type: application/rss+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    echo $xml->outputMemory();
} catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Feed temporarily unavailable.';
}
