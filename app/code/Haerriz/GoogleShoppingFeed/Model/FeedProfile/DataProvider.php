<?php
namespace Haerriz\GoogleShoppingFeed\Model\FeedProfile;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\Collection;

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        foreach ($items as $model) {
            $data = $model->getData();
            
            // Enterprise Feature: Deserialize dynamic rows data
            if (!empty($data['attributes_mapping_serialized'])) {
                $data['attributes_mapping'] = json_decode($data['attributes_mapping_serialized'], true);
            }
            if (!empty($data['conditions_serialized'])) {
                $data['conditions'] = json_decode($data['conditions_serialized'], true);
            }
            
            $this->loadedData[$model->getId()] = $data;
        }
        $data = $this->dataRetriever();
        if (!empty($data)) {
            $model = $this->collection->getNewEmptyItem();
            $model->setData($data);
            $this->loadedData[$model->getId()] = $model->getData();
        }
        return $this->loadedData;
    }

    private function dataRetriever()
    {
        // Add custom logic here for session fallback if needed
        return [];
    }
}
