<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Haerriz\GoogleShoppingFeed\Model\FeedJob as Model;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
