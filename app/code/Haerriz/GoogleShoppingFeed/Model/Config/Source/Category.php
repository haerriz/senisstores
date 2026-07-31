<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Category implements OptionSourceInterface
{
    private $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('name')->addFieldToFilter('level', ['gt' => 0]);
        $collection->setOrder('path', 'ASC');
        $options = [];
        foreach ($collection as $category) {
            $options[] = [
                'value' => (int)$category->getId(),
                'label' => str_repeat('— ', max(0, (int)$category->getLevel() - 1))
                    . (string)$category->getName(),
            ];
        }
        return $options;
    }
}
