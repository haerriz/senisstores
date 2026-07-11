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

$conn = $obj->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$productAction = $obj->get(\Magento\Catalog\Model\Product\Action::class);

foreach (['short_description', 'description'] as $attrCode) {
    $attrId = (int) $conn->fetchOne(
        "SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4",
        [$attrCode]
    );

    $rows = $conn->fetchAll("
        SELECT s1.entity_id, s1.value
        FROM catalog_product_entity_text s1
        LEFT JOIN catalog_product_entity_text s0
            ON s0.entity_id = s1.entity_id AND s0.attribute_id = s1.attribute_id AND s0.store_id = 0
        WHERE s1.attribute_id = ? AND s1.store_id = 1
            AND s1.value IS NOT NULL AND s1.value != ''
            AND (s0.value IS NULL OR s0.value = '')
    ", [$attrId]);

    foreach ($rows as $row) {
        $productAction->updateAttributes(
            [(int) $row['entity_id']],
            [$attrCode => $row['value']],
            0
        );
        echo "Synced {$attrCode} to store 0 for product {$row['entity_id']}\n";
    }
}

echo "Done.\n";
