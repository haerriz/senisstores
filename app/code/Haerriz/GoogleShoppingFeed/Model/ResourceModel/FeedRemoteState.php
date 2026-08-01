<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FeedRemoteState extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('haerriz_google_shopping_feed_remote_state', 'state_id');
    }
}
