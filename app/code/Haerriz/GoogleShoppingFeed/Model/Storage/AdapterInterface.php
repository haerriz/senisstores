<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

interface AdapterInterface
{
    /**
     * Upload the feed file to the specified delivery destination.
     *
     * @param FeedProfileInterface $profile
     * @param string $localFilePath Relative path to media directory
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function upload(FeedProfileInterface $profile, $localFilePath);
}
