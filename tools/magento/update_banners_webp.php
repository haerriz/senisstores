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
$mediaBase = '/home/u434561653/domains/senisstores.com/public_html/pub/media/';

$rows = $conn->fetchAll('SELECT id, title, image FROM weltpixel_owlcarouselslider_banners');

foreach ($rows as $row) {
    $image = $row['image'];
    if (!preg_match('/\.(jpe?g|png)$/i', $image)) {
        continue;
    }

    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image);
    $webpPath = $mediaBase . $webp;

    if (!file_exists($webpPath)) {
        echo "SKIP banner {$row['id']}: WebP not found at $webp\n";
        continue;
    }

    $conn->update(
        'weltpixel_owlcarouselslider_banners',
        ['image' => $webp],
        ['id = ?' => $row['id']]
    );

    echo "Updated banner {$row['id']} ({$row['title']}): $image -> $webp\n";
}

echo "Done.\n";
