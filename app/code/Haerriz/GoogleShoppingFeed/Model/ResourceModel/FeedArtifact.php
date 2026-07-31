<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FeedArtifact extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('haerriz_google_shopping_feed_artifact', 'artifact_id');
    }
}
