<?php
namespace Haerriz\GoogleShoppingFeed\Model\Generation;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

class ProfileSnapshot
{
    public function take(FeedProfileInterface $profile): array
    {
        return [
            'profile_id' => (int)$profile->getId(),
            'name' => (string)$profile->getName(),
            'feed_type' => (string)$profile->getFeedType(),
            'filename' => (string)$profile->getFilename(),
            'store_id' => (int)$profile->getStoreId(),
            'currency' => (string)$profile->getCurrency(),
            'delivery_type' => (string)$profile->getDeliveryType(),
            'status' => (int)$profile->getStatus(),
            'taken_at' => date('c'),
        ];
    }

    /** @deprecated Use take() */
    public function snapshot(array $data): array
    {
        return $data;
    }
}
