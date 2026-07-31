<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\ProductProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Psr\Log\LoggerInterface;

class ProductProvider implements ProductProviderInterface
{
    private $productCollectionFactory;
    private $stockHelper;
    private $configReader;
    private $logger;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        StockHelper $stockHelper,
        ProfileConfigReader $configReader,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockHelper              = $stockHelper;
        $this->configReader             = $configReader;
        $this->logger                   = $logger;
    }

    public function getCollection(
        FeedProfileInterface $profile,
        $rule = null,
        $afterEntityId = 0,
        $pageSize = 500
    ): Collection {
        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect([
            'sku', 'name', 'price', 'special_price', 'special_from_date', 'special_to_date',
            'image', 'small_image', 'thumbnail', 'description', 'short_description',
            'status', 'visibility', 'tax_class_id', 'weight', 'manufacturer', 'color', 'size',
            'meta_title', 'meta_description', 'meta_keyword', 'url_key',
            'quantity_and_stock_status'
        ]);

        $collection->addAttributeToFilter(
            'status',
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
        );

        $collection->addAttributeToFilter('visibility', [
            'in' => [
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH,
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
            ]
        ]);

        // FIX 8: ProfileConfigReader::get() — read store_id from profile config
        $storeId = (int)$this->configReader->get($profile, 'store_id', (int)$profile->getStoreId());
        $collection->setStoreId($storeId);

        // FIX 9: ProfileConfigReader::getBoolean() — read include_out_of_stock flag
        $includeOutOfStock = $this->configReader->getBoolean($profile, 'include_out_of_stock', false);
        if (!$includeOutOfStock) {
            $this->stockHelper->addInStockFilterToCollection($collection);
            $this->logger->debug("ProductProvider: Out-of-stock products excluded for profile [{$profile->getName()}]");
        } else {
            $this->logger->debug("ProductProvider: Out-of-stock products INCLUDED for profile [{$profile->getName()}]");
        }

        // FIX 10: ProfileConfigReader::getIntList() — read exclude_category_ids
        $excludedCatIds = $this->configReader->getIntList($profile, 'exclude_category_ids');
        if (empty($excludedCatIds)) {
            // Fallback to profile's own field
            $raw = $profile->getExcludeCategoryIds();
            if ($raw) {
                $excludedCatIds = array_filter(array_map('intval', explode(',', (string)$raw)));
            }
        }
        if (!empty($excludedCatIds)) {
            $collection->addCategoriesFilter(['nin' => array_values($excludedCatIds)]);
            $this->logger->debug("ProductProvider: Excluding categories [" . implode(',', $excludedCatIds) . "] for profile [{$profile->getName()}]");
        }

        // KEYSET PAGINATION — critical: entity_id > $afterEntityId prevents infinite loop
        if ($afterEntityId > 0) {
            $collection->addFieldToFilter('entity_id', ['gt' => $afterEntityId]);
        }

        $collection->addOrder('entity_id', Collection::SORT_ORDER_ASC);
        $collection->setPageSize((int)$pageSize);
        $collection->setCurPage(1);

        $this->logger->debug("ProductProvider: getCollection() after_id={$afterEntityId}, page_size={$pageSize}, store={$storeId}");

        return $collection;
    }
}
