<?php

declare(strict_types=1);

namespace Haerriz\Seo\Plugin\Sitemap;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Sitemap\Model\ItemProvider\Category as CategoryItemProvider;

/** Keeps empty/noindex category URLs out of the XML sitemap. */
class FilterNoindexCategories
{
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    public function afterGetItems(CategoryItemProvider $subject, array $items, $storeId): array
    {
        $products = $this->productCollectionFactory->create();
        $products->setStoreId((int) $storeId)
            ->addStoreFilter((int) $storeId)
            ->addAttributeToFilter('status', 1)
            ->joinField(
                'seo_category_id',
                'catalog_category_product',
                'category_id',
                'product_id=entity_id',
                null,
                'inner'
            );
        $select = clone $products->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns('at_seo_category_id.category_id')
            ->distinct(true);
        $categoryIds = array_map('intval', $products->getConnection()->fetchCol($select));
        if (!$categoryIds) {
            return [];
        }

        $categories = $this->categoryCollectionFactory->create();
        $categories->setStoreId((int) $storeId)
            ->addAttributeToSelect(['url_path', 'is_active'])
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', ['in' => $categoryIds]);

        $allowedPaths = [];
        foreach ($categories as $category) {
            $path = $this->normalizePath((string) $category->getUrl());
            if ($path !== '') {
                $allowedPaths[$path] = true;
            }
        }

        return array_values(array_filter(
            $items,
            fn ($item): bool => isset($allowedPaths[$this->normalizePath((string) $item->getUrl())])
        ));
    }

    private function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return trim(is_string($path) ? $path : $url, '/');
    }
}
