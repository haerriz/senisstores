<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Taxonomy;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

class SaveMappingAjax extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_management';

    private $jsonFactory;
    private $connection;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->connection = $resourceConnection->getConnection();
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        $magentoId = (int)$this->getRequest()->getParam('magento_category_id');
        $googleId = (int)$this->getRequest()->getParam('google_category_id'); // Can be 0 to delete

        if (!$magentoId) {
            return $result->setData(['success' => false, 'message' => 'Invalid Magento Category ID']);
        }

        try {
            $mappingTable = $this->connection->getTableName('haerriz_google_shopping_feed_category_mapping');
            
            // Create table if it doesn't exist to ensure safety during phase deployment
            if (!$this->connection->isTableExists($mappingTable)) {
                $this->connection->query("
                    CREATE TABLE `{$mappingTable}` (
                        `magento_category_id` int(10) unsigned NOT NULL,
                        `google_category_id` int(10) unsigned NOT NULL,
                        PRIMARY KEY (`magento_category_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                ");
            }

            if ($googleId > 0) {
                $this->connection->insertOnDuplicate($mappingTable, [
                    'magento_category_id' => $magentoId,
                    'google_category_id' => $googleId
                ], ['google_category_id']);
            } else {
                // Delete mapping
                $this->connection->delete($mappingTable, ['magento_category_id = ?' => $magentoId]);
            }

            return $result->setData(['success' => true]);

        } catch (\Exception $e) {
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
