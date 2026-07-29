<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile as Model;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile as ResourceModel;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'profile_id';

    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
