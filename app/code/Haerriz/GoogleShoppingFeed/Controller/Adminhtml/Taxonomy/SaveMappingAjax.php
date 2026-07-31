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
        $googleId = $this->getRequest()->getParam('google_category_id'); // Can be string or int

        if (!$magentoId) {
            return $result->setData(['success' => false, 'message' => 'Invalid Magento Category ID']);
        }

        try {
            $mappingTable = $this->connection->getTableName('haerriz_google_shopping_feed_category_mapping');
            
            // Delete mapping if 0 or empty
            if (empty($googleId) || $googleId === '0') {
                $this->connection->delete($mappingTable, ['magento_category_id = ?' => $magentoId]);
                return $result->setData(['success' => true]);
            }

            // Otherwise Insert/Update
            $this->connection->insertOnDuplicate($mappingTable, [
                'magento_category_id' => $magentoId,
                'taxonomy_path' => $googleId
            ], ['taxonomy_path']);

            return $result->setData(['success' => true]);

        } catch (\Exception $e) {
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
