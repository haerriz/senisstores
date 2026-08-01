<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact;

use Haerriz\GoogleShoppingFeed\Model\FeedArtifact as Model;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
