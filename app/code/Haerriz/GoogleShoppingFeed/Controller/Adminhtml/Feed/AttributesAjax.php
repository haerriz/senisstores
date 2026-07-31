<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;

class AttributesAjax extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $jsonFactory;
    private $attributeCollectionFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CollectionFactory $attributeCollectionFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        try {
            $collection = $this->attributeCollectionFactory->create();
            $collection->addFieldToSelect(['attribute_code', 'frontend_label']);
            $collection->setOrder('frontend_label', 'ASC');
            
            $attributes = [];
            foreach ($collection as $attr) {
                if ($attr->getFrontendLabel()) {
                    $attributes[] = [
                        'code' => $attr->getAttributeCode(),
                        'label' => $attr->getFrontendLabel()
                    ];
                }
            }
            
            // Add custom static attributes that might be useful
            array_unshift($attributes, ['code' => 'entity_id', 'label' => 'Product ID']);
            array_unshift($attributes, ['code' => 'sku', 'label' => 'SKU']);
            array_unshift($attributes, ['code' => 'qty', 'label' => 'Quantity (Stock)']);
            array_unshift($attributes, ['code' => 'is_in_stock', 'label' => 'Stock Status']);

            return $result->setData(['success' => true, 'attributes' => $attributes]);
        } catch (\Exception $e) {
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
