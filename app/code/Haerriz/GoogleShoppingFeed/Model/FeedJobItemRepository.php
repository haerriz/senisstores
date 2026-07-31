<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\FeedJobItemRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedJobItemInterface;

class FeedJobItemRepository implements FeedJobItemRepositoryInterface
{
    public function save(FeedJobItemInterface $item)
    {
        return $item;
    }
}
