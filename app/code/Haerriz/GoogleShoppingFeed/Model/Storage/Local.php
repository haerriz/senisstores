<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;

class Local implements AdapterInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @inheritdoc
     */
    public function upload(FeedProfileInterface $profile, $localFilePath)
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if (!$directory->isReadable($localFilePath)) {
            throw new LocalizedException(
                __('Local feed file is missing or not readable at: %1', $localFilePath)
            );
        }
        return true;
    }
}
