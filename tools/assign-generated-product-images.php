<?php
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

require dirname(__DIR__) . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
try {
    $objectManager->get(State::class)->setAreaCode('adminhtml');
} catch (Throwable) {
}

$repository = $objectManager->get(ProductRepositoryInterface::class);
$galleryProcessor = $objectManager->get(Processor::class);
$sourceDir = BP . '/pub/media/import/generated-product-images';

$assignments = [
    'Focus Screw Driver 521' => ['focus-521-lifestyle.png'],
    '1 Ltr Aluminium Measuring cup' => ['measuring-cup-1l-scale.png', 'measuring-cup-five-size-comparison.png'],
    '500ml Aluminium Measuring cup' => ['measuring-cup-five-size-comparison.png'],
    '200ml Aluminium Measuring cup' => ['measuring-cup-five-size-comparison.png'],
    '100ml Aluminium Measuring cup' => ['measuring-cup-five-size-comparison.png'],
    '50ml Aluminium Measuring cup' => ['measuring-cup-five-size-comparison.png'],
    'Measuring cup set of 5 combo' => ['measuring-cup-five-size-comparison.png'],
    '8 g GI wire' => ['gi-wire-coil-scale.png'],
    '10 g GI wire' => ['gi-wire-coil-scale.png'],
    '12 g GI wire' => ['gi-wire-coil-scale.png'],
    '14 g GI wire' => ['gi-wire-coil-scale.png'],
    '16 g GI wire' => ['gi-wire-coil-scale.png'],
    '18 g GI wire' => ['gi-wire-coil-scale.png'],
    '20 g GI wire' => ['gi-wire-coil-scale.png'],
    '24 g GI wire' => ['gi-wire-coil-scale.png'],
];

foreach ($assignments as $sku => $files) {
    $product = $repository->get($sku, true, 1, true);
    $existing = [];
    foreach ((array)$product->getMediaGallery('images') as $image) {
        $existing[] = basename((string)($image['file'] ?? ''));
    }

    $changed = false;
    foreach ($files as $file) {
        $path = $sourceDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException('Missing generated image: ' . $path);
        }
        if (in_array($file, $existing, true)) {
            continue;
        }
        $galleryProcessor->addImage($product, $path, null, false, false);
        $changed = true;
    }
    if ($changed) {
        $repository->save($product);
        echo "Updated {$sku}\n";
    } else {
        echo "Already current {$sku}\n";
    }
}
