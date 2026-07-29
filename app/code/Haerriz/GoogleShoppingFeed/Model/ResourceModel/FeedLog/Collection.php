<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Haerriz\GoogleShoppingFeed\Model\FeedLog as Model;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
