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
    'GI Wire-1' => ['gi-wire-coil-scale.png'],
    'GI Wire-2' => ['gi-wire-coil-scale.png'],
    'GI Wire-3' => ['gi-wire-coil-scale.png'],
    'GI Wire-4' => ['gi-wire-coil-scale.png'],
    'GI Wire-5' => ['gi-wire-coil-scale.png'],
    'PVC Wire-1' => ['pvc-wire-coil-lifestyle.png'],
    'PVC Wire-2' => ['pvc-wire-coil-lifestyle.png'],
    'PVC Wire-3' => ['pvc-wire-coil-lifestyle.png'],
    'PVC Wire-4' => ['pvc-wire-coil-lifestyle.png'],
    'PVC Wire-5' => ['pvc-wire-coil-lifestyle.png'],
    'PVC Wire' => ['pvc-wire-coil-lifestyle.png'],
    'Spade with handle - மண்வெட்டி' => ['spade-with-handle-garden.png'],
    'Crowbar - கடப்பாரை - kadapaarai - 5.5 feet' => ['crowbar-workshop.png'],
    'Crowbar - கடப்பாரை - kadapaarai - 4.5 feet' => ['crowbar-workshop.png'],
    'Electronic weighing Machine - (10Kgs Max)' => ['weighing-machine-10kg-shop.png'],
    'Garden scissors' => ['garden-scissors-lifestyle.png'],
    'shook-2-5-inch-small' => ['s-hook-small-lifestyle.png'],
    'Birla White Portland cement 5kg' => ['birla-white-5kg-clean.png'],
    'Birla White Portland cement 1kg' => ['birla-white-1kg-clean.png'],
    'Spade (மண்வெட்டி)' => ['spade-head-lifestyle.png'],
    'Electronic Weighing machine (Maximum 20Kgs)' => ['weighing-machine-20kg-shop.png'],
    'Electronic Weighing machine [250mm X 250mm] (Maximum 50Kgs)' => ['weighing-machine-50kg-shop.png'],
    'mseal-phataphat-25g' => ['mseal-phataphat-clean.png'],
    'mseal-regular-90g' => ['mseal-regular-clean.png'],
    'ezy-seal' => ['ezy-seal-clean.png'],
    'mini-jhola-chain-heavy' => ['jhola-chain-comparison.png'],
    'mini-jhola-chain-regular' => ['jhola-chain-comparison.png'],
    'steel-karni-pvc-handle' => ['steel-karni-lifestyle.png'],
    'shook-4-inch-large' => ['s-hook-large-comparison.png'],
    'shook-4-inch-large-spl' => ['s-hook-large-comparison.png'],
    'shook-6-inch-xl' => ['s-hook-large-comparison.png'],
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
        $stem = pathinfo($file, PATHINFO_FILENAME);
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $alreadyAssigned = array_filter(
            $existing,
            static fn(string $existingFile): bool => (bool)preg_match(
                '/^' . preg_quote($stem, '/') . '(?:_\\d+)*\\.' . preg_quote($extension, '/') . '$/i',
                $existingFile
            )
        );
        if ($alreadyAssigned) {
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
