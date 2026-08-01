<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedRemoteStateInterface;

interface FeedRemoteStateRepositoryInterface
{
    /**
     * @param FeedRemoteStateInterface $state
     * @return FeedRemoteStateInterface
     */
    public function save(FeedRemoteStateInterface $state);

    /**
     * @param string $offerId
     * @param int|string $profileId
     * @return FeedRemoteStateInterface
     */
    public function getByOfferIdAndProfile(string $offerId, $profileId): FeedRemoteStateInterface;

    /**
     * @param int|null $profileId
     * @return array<string, int>
     */
    public function getStatusCounts(?int $profileId = null): array;
}
