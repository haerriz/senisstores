<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedArtifactInterface;
use Magento\Framework\Model\AbstractModel;

class FeedArtifact extends AbstractModel implements FeedArtifactInterface
{
    protected function _construct()
    {
        $this->_init(\Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact::class);
    }

    public function setProfileId(int $id) { return $this->setData('profile_id', $id); }
    public function setFilePath(string $path) { return $this->setData('file_path', $path); }
    public function setFileSize(int $size) { return $this->setData('file_size', $size); }
    public function setChecksum(string $checksum) { return $this->setData('checksum', $checksum); }
    public function setExportedCount(int $count) { return $this->setData('exported_count', $count); }
    public function setCreatedAt(string $time) { return $this->setData('created_at', $time); }
}
