<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

use Magento\Framework\App\Bootstrap;
use Magento\Newsletter\Model\Subscriber;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);

try {
    $state->setAreaCode('adminhtml');
} catch (\Exception $e) {
    // Area already set.
}

$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
$storeId = (int) $storeManager->getStore()->getId();

echo "=== Newsletter audit ===\n";
echo 'Store ID: ' . $storeId . "\n";
echo 'Customers: ' . $connection->fetchOne('SELECT COUNT(*) FROM customer_entity') . "\n";
echo 'Subscribers total: ' . $connection->fetchOne('SELECT COUNT(*) FROM newsletter_subscriber') . "\n";
echo 'Subscribed (status=1): ' . $connection->fetchOne(
    'SELECT COUNT(*) FROM newsletter_subscriber WHERE subscriber_status = ' . Subscriber::STATUS_SUBSCRIBED
) . "\n";

echo "\n=== Top products by qty ordered ===\n";
$rows = $connection->fetchAll(
    "SELECT cpe.entity_id, cpe.sku,
            COALESCE(name.value, cpe.sku) AS name,
            SUM(COALESCE(soi.qty_ordered, 0)) AS qty_ordered
     FROM sales_order_item soi
     INNER JOIN catalog_product_entity cpe ON cpe.entity_id = soi.product_id
     LEFT JOIN catalog_product_entity_varchar name
       ON name.entity_id = cpe.entity_id
      AND name.attribute_id = (
          SELECT attribute_id FROM eav_attribute
          WHERE attribute_code = 'name' AND entity_type_id = 4
      )
      AND name.store_id = 0
     WHERE soi.parent_item_id IS NULL
     GROUP BY cpe.entity_id, cpe.sku, name.value
     ORDER BY qty_ordered DESC
     LIMIT 8"
);

foreach ($rows as $row) {
    echo sprintf(
        "- %s | SKU: %s | Qty sold: %s\n",
        $row['name'],
        $row['sku'],
        $row['qty_ordered']
    );
}

if (!$rows) {
    echo "No order history; will fall back to enabled catalog products.\n";
    $rows = $connection->fetchAll(
        "SELECT cpe.entity_id, cpe.sku, COALESCE(name.value, cpe.sku) AS name, 0 AS qty_ordered
         FROM catalog_product_entity cpe
         INNER JOIN catalog_product_entity_int status
           ON status.entity_id = cpe.entity_id
          AND status.attribute_id = (
              SELECT attribute_id FROM eav_attribute
              WHERE attribute_code = 'status' AND entity_type_id = 4
          )
          AND status.store_id = 0
          AND status.value = 1
         LEFT JOIN catalog_product_entity_varchar name
           ON name.entity_id = cpe.entity_id
          AND name.attribute_id = (
              SELECT attribute_id FROM eav_attribute
              WHERE attribute_code = 'name' AND entity_type_id = 4
          )
          AND name.store_id = 0
         ORDER BY cpe.entity_id DESC
         LIMIT 8"
    );
    foreach ($rows as $row) {
        echo sprintf("- %s | SKU: %s\n", $row['name'], $row['sku']);
    }
}
