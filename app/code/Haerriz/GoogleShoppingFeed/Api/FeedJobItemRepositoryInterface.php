<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface FeedJobItemRepositoryInterface
{
    public function save(\Haerriz\GoogleShoppingFeed\Api\Data\FeedJobItemInterface $item);
}
