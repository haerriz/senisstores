<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\FeedRemoteStateRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedRemoteStateInterface;

class FeedRemoteStateRepository implements FeedRemoteStateRepositoryInterface
{
    public function save(FeedRemoteStateInterface $state)
    {
        return $state;
    }
}
