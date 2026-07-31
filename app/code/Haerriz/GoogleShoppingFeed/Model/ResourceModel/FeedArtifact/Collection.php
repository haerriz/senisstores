<?php
namespace Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Haerriz\GoogleShoppingFeed\Model\FeedArtifact::class,
            \Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact::class
        );
    }
}
