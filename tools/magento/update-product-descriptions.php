<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
require '/home/u434561653/domains/senisstores.com/public_html/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$obj->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

/** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
$productRepository = $obj->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
/** @var \Magento\Catalog\Model\Product\Action $productAction */
$productAction = $obj->get(\Magento\Catalog\Model\Product\Action::class);

$GLOBALS['storeTagline'] = "Available at Seni's Stores, Theni — trusted hardware shop on Periyakulam Road.";

/**
 * @param string $name
 * @return array{short:string,description:string}|null
 */
function buildProductCopy($name)
{
    $name = trim($name);

    if (preg_match('/^(\d+)\s*g\s*GI\s*wire$/i', $name, $m)) {
        $gauge = $m[1];
        return [
            'short' => "Buy {$gauge}g GI binding wire in Theni at Seni's Stores. Galvanised iron wire for construction, fencing, tying reinforcement and everyday hardware use.",
            'description' => "<p><strong>{$gauge}g GI wire</strong> (galvanised iron binding wire) suitable for construction sites, grills, fencing, packaging and general tying work. The zinc coating helps resist rust in outdoor and masonry applications.</p>"
                . "<p>Choose {$gauge} gauge based on your job — heavier gauges suit structural binding, lighter gauges suit fine tying work. {$GLOBALS['storeTagline']}</p>"
                . "<p>Wholesale and retail orders welcome. Call for bulk pricing and stock availability.</p>",
        ];
    }

    if (preg_match('/^GI\s*Wire-\d+$/i', $name)) {
        return [
            'short' => "Quality GI binding wire from Seni's Stores, Theni. Galvanised iron wire for construction, fencing and hardware applications.",
            'description' => "<p><strong>GI binding wire</strong> for tying reinforcement bars, mesh, fencing and general workshop use. Corrosion-resistant galvanised finish for dependable outdoor performance.</p>"
                . "<p>{$GLOBALS['storeTagline']} Multiple gauges and brands may be available — contact us for current stock.</p>",
        ];
    }

    if (preg_match('/^PVC\s*Wire(-\d+)?$/i', $name)) {
        return [
            'short' => "PVC coated wire at Seni's Stores, Theni. Flexible, weather-resistant wire for fencing, garden and tying applications.",
            'description' => "<p><strong>PVC coated wire</strong> offers a protective plastic coating over steel wire for improved weather resistance and safer handling. Commonly used for fencing, plant support, crafts and light-duty tying.</p>"
                . "<p>{$GLOBALS['storeTagline']} Suitable for home, farm and workshop projects across Theni and nearby areas.</p>",
        ];
    }

    if (stripos($name, 'Birla White') !== false && stripos($name, 'cement') !== false) {
        $size = stripos($name, '5kg') !== false ? '5 kg' : '1 kg';
        return [
            'short' => "Birla White Portland cement {$size} at Seni's Stores, Theni. Premium white cement for putty, finishing and decorative masonry work.",
            'description' => "<p><strong>Birla White Portland cement ({$size})</strong> is used for wall putty, smooth finishes, tile joint filling and decorative cement work where a bright white surface is required.</p>"
                . "<p>Ideal for painters, masons and home renovation projects. {$GLOBALS['storeTagline']}</p>",
        ];
    }

    if (stripos($name, 'Spade') !== false || stripos($name, 'மண்வெட்டி') !== false) {
        $withHandle = stripos($name, 'handle') !== false;
        return [
            'short' => ($withHandle
                ? "Spade with handle (மண்வெட்டி) from Seni's Stores, Theni. Ready-to-use garden and digging tool."
                : "Heavy-duty spade (மண்வெட்டி) from Seni's Stores, Theni. Essential tool for digging and garden work."),
            'description' => "<p><strong>Spade / மண்வெட்டி</strong> for digging, soil shifting, gardening and construction site work. " . ($withHandle
                ? "Supplied with handle for immediate use."
                : "Durable blade for demanding outdoor tasks.") . "</p>"
                . "<p>{$GLOBALS['storeTagline']} Popular with farmers, gardeners and contractors in Theni district.</p>",
        ];
    }

    if (stripos($name, 'Crowbar') !== false || stripos($name, 'kadapaarai') !== false || stripos($name, 'கடப்பாரை') !== false) {
        $length = 'standard';
        if (preg_match('/(\d+\.?\d*)\s*feet/i', $name, $m)) {
            $length = $m[1] . ' feet';
        }
        return [
            'short' => "Crowbar (கடப்பாரை / kadapaarai) {$length} from Seni's Stores, Theni. Leverage tool for demolition, lifting and prying work.",
            'description' => "<p><strong>Crowbar / கடப்பாரை</strong> ({$length}) for breaking hard ground, lifting stones, aligning frames and demolition tasks. Forged steel construction for strength and leverage.</p>"
                . "<p>{$GLOBALS['storeTagline']} A must-have tool for masons, fabricators and site workers.</p>",
        ];
    }

    if (stripos($name, 'Measuring cup') !== false) {
        if (stripos($name, 'combo') !== false || stripos($name, 'set') !== false) {
            return [
                'short' => "Aluminium measuring cup set (5 pieces) at Seni's Stores, Theni. Accurate liquid measurement for kitchen, paint and workshop use.",
                'description' => "<p><strong>Measuring cup set of 5</strong> in aluminium for consistent liquid measurement. Useful in kitchens, paint mixing, chemical dosing and small workshop tasks.</p>"
                    . "<p>{$GLOBALS['storeTagline']} Durable, easy to clean and ideal for home and commercial use.</p>",
            ];
        }
        $size = trim(preg_replace('/Aluminium\s+Measuring\s+cup/i', '', $name));
        return [
            'short' => "Aluminium measuring cup {$size} from Seni's Stores, Theni. Precise liquid measurement for kitchen, paint and industrial use.",
            'description' => "<p><strong>Aluminium measuring cup ({$size})</strong> for accurate pouring and mixing of liquids. Lightweight, rust-resistant aluminium build.</p>"
                . "<p>{$GLOBALS['storeTagline']} Suitable for households, bakeries, painters and workshops.</p>",
        ];
    }

    if (stripos($name, 'weighing') !== false || stripos($name, 'Weighing') !== false) {
        $capacity = 'multi-capacity';
        if (preg_match('/(\d+)\s*Kgs?/i', $name, $m)) {
            $capacity = $m[1] . ' kg';
        }
        $platform = stripos($name, '250mm') !== false ? '250 mm × 250 mm platform. ' : '';
        return [
            'short' => "Electronic weighing machine (max {$capacity}) at Seni's Stores, Theni. Digital scale for shop, kitchen and commercial weighing.",
            'description' => "<p><strong>Electronic weighing machine</strong> with maximum capacity up to {$capacity}. {$platform}Ideal for retail counters, warehouses, kitchens and parcel weighing.</p>"
                . "<p>{$GLOBALS['storeTagline']} Reliable digital readout for everyday commercial and home use.</p>",
        ];
    }

    if (stripos($name, 'Garden scissors') !== false) {
        return [
            'short' => "Caltex garden scissors with PVC handle from Seni's Stores, Theni. Sharp pruning scissors for plants, hedges and garden trimming.",
            'description' => "<p><strong>Caltex garden scissors</strong> with comfortable PVC handle for pruning plants, trimming hedges and light garden cutting tasks.</p>"
                . "<p>{$GLOBALS['storeTagline']} A handy tool for home gardeners and landscapers in Theni.</p>",
        ];
    }

    if (stripos($name, 'Screw Driver') !== false || stripos($name, 'Screwdriver') !== false) {
        return [
            'short' => "Focus screw driver 521 from Seni's Stores, Theni. Double-sided +/− tip, 3 mm × 125 mm for household and workshop use.",
            'description' => "<p><strong>Focus Screw Driver 521</strong> — manual screwdriver with double-sided + and − head. Size: 125 mm × 3 mm. Comfortable grip for assembly, repair and DIY work.</p>"
                . "<p>{$GLOBALS['storeTagline']} Essential hand tool for electricians, carpenters and home maintenance.</p>",
        ];
    }

    return null;
}

$conn = $obj->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$productIds = $conn->fetchCol("
    SELECT cpe.entity_id FROM catalog_product_entity cpe
    INNER JOIN catalog_product_entity_int st ON st.entity_id = cpe.entity_id
        AND st.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code='status' AND entity_type_id=4)
        AND st.store_id = 0 AND st.value = 1
    ORDER BY cpe.entity_id
");

$updated = 0;
$skipped = 0;

foreach ($productIds as $productId) {
    $product = $productRepository->getById((int) $productId, false, 0, true);
    $name = (string) $product->getName();
    $currentDesc = trim(strip_tags((string) $product->getDescription()));
    $currentShort = trim(strip_tags((string) $product->getShortDescription()));

    $needsDesc = $currentDesc === '' || ($productId == 1 && strlen($currentDesc) < 80);
    $needsShort = $currentShort === '';

    if (!$needsDesc && !$needsShort) {
        $skipped++;
        continue;
    }

    $copy = buildProductCopy($name);
    if ($copy === null) {
        echo "SKIP (no template): {$name}\n";
        $skipped++;
        continue;
    }

    if ($needsShort) {
        $product->setShortDescription($copy['short']);
    }

    if ($needsDesc) {
        $product->setDescription($copy['description']);
    }

    $attrs = [];
    if ($needsShort) {
        $attrs['short_description'] = $copy['short'];
    }
    if ($needsDesc) {
        $attrs['description'] = $copy['description'];
    }

    foreach ([0, 1] as $storeId) {
        $productAction->updateAttributes([(int) $productId], $attrs, $storeId);
    }

    $updated++;
    echo "UPDATED: [{$productId}] {$name}\n";
}

echo "\nUpdated: {$updated}\nSkipped: {$skipped}\nDone.\n";
