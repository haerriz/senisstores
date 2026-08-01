<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Taxonomy;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;

class TreeAjax extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_management';

    private $jsonFactory;
    private $categoryCollectionFactory;
    private $connection;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->connection = $resourceConnection->getConnection();
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        try {
            // 1. Fetch Magento Categories (simplified list for UI mapping)
            $magentoCategories = [];
            $collection = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('name')
                ->addFieldToFilter('is_active', 1)
                ->setOrder('path', 'ASC');

            foreach ($collection as $category) {
                if ($category->getLevel() > 1) { // Skip root
                    $magentoCategories[] = [
                        'id' => $category->getId(),
                        'name' => str_repeat('-- ', $category->getLevel() - 2) . $category->getName(),
                        'level' => $category->getLevel()
                    ];
                }
            }

            // 2. Fetch Google Taxonomy List
            $taxonomyTable = $this->connection->getTableName('haerriz_google_shopping_feed_taxonomy');
            $googleCategories = [];
            
            // Check if table exists before querying
            if ($this->connection->isTableExists($taxonomyTable)) {
                $googleCategories = $this->connection->fetchAll(
                    "SELECT category_id as id, full_path as name FROM {$taxonomyTable} ORDER BY full_path ASC"
                );
            }
            
            // Fallback mock data if the table is empty for UI testing
            if (empty($googleCategories)) {
                $googleCategories = [
                    ['id' => '166', 'name' => 'Apparel & Accessories'],
                    ['id' => '2271', 'name' => 'Apparel & Accessories > Clothing'],
                    ['id' => '212', 'name' => 'Apparel & Accessories > Clothing > Dresses'],
                    ['id' => '214', 'name' => 'Apparel & Accessories > Clothing > Shirts & Tops']
                ];
            }

            // 3. Fetch Existing Mappings
            $mappingTable = $this->connection->getTableName('haerriz_google_shopping_feed_category_mapping');
            $mappings = [];
            if ($this->connection->isTableExists($mappingTable)) {
                $rawMappings = $this->connection->fetchAll("SELECT magento_category_id, google_category_id FROM {$mappingTable}");
                foreach ($rawMappings as $row) {
                    $mappings[$row['magento_category_id']] = $row['google_category_id'];
                }
            }

            return $result->setData([
                'success' => true,
                'magento_categories' => $magentoCategories,
                'google_categories' => $googleCategories,
                'mappings' => $mappings
            ]);

        } catch (\Exception $e) {
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
