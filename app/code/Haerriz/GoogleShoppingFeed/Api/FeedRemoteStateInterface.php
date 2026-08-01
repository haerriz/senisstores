<?php
namespace Haerriz\GoogleShoppingFeed\Api;

/**
 * Lightweight remote-state contract used by Merchant sync helpers.
 * Full persistence API lives in Api\Data\FeedRemoteStateInterface.
 */
interface FeedRemoteStateInterface
{
    public function getProductId(): int;

    public function getRemoteStatus(): string;
}
