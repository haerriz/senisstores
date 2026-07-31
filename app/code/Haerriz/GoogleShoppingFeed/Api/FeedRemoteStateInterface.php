<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface FeedRemoteStateInterface
{
    public function getProductId(): int;
    public function getRemoteStatus(): string;
}
