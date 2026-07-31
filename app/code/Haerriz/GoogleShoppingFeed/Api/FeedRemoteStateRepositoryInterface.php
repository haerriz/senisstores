<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface FeedRemoteStateRepositoryInterface
{
    public function save(\Haerriz\GoogleShoppingFeed\Api\Data\FeedRemoteStateInterface $state);
}
