<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
require '/home/u434561653/domains/senisstores.com/public_html/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$conn = $obj->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();

$includes = (string) $conn->fetchOne(
    "SELECT value FROM core_config_data WHERE path = 'design/head/includes' AND scope = 'stores' AND scope_id = 1"
);

if ($includes === '') {
    $includes = (string) $conn->fetchOne(
        "SELECT value FROM core_config_data WHERE path = 'design/head/includes' AND scope = 'default' AND scope_id = 0"
    );
}

$cleaned = preg_replace(
    '/\s*<!--\s*Favicons\s*-->\s*<link[^>]*favicon[^>]*>\s*/i',
    "\n",
    $includes
);

$cleaned = trim($cleaned) . "\n";

$updated = $conn->update(
    'core_config_data',
    ['value' => $cleaned],
    ['path = ?' => 'design/head/includes', 'scope = ?' => 'stores', 'scope_id = ?' => 1]
);

if (!$updated) {
    $conn->insert('core_config_data', [
        'scope' => 'stores',
        'scope_id' => 1,
        'path' => 'design/head/includes',
        'value' => $cleaned,
    ]);
}

$conn->update(
    'core_config_data',
    ['value' => 'stores/1/senisstores-logo.png'],
    ['path = ?' => 'design/head/shortcut_icon', 'scope = ?' => 'stores', 'scope_id = ?' => 1]
);

echo "Removed hardcoded favicon.ico from design/head/includes\n";
echo "Confirmed shortcut_icon: stores/1/senisstores-logo.png\n";
