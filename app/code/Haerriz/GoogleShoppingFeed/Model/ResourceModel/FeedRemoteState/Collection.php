<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedRemoteState;

use Haerriz\GoogleShoppingFeed\Model\FeedRemoteState;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedRemoteState as FeedRemoteStateResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(FeedRemoteState::class, FeedRemoteStateResource::class);
    }
}
