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

    public function getId()
    {
        return $this->getData(self::ARTIFACT_ID);
    }

    public function setId($id)
    {
        return $this->setData(self::ARTIFACT_ID, $id);
    }

    public function getProfileId()
    {
        return $this->getData(self::PROFILE_ID);
    }

    public function setProfileId($profileId)
    {
        return $this->setData(self::PROFILE_ID, $profileId);
    }

    public function getJobId()
    {
        return $this->getData(self::JOB_ID);
    }

    public function setJobId($jobId)
    {
        return $this->setData(self::JOB_ID, $jobId);
    }

    public function getFilePath()
    {
        return $this->getData(self::FILE_PATH);
    }

    public function setFilePath($filePath)
    {
        return $this->setData(self::FILE_PATH, $filePath);
    }

    public function getChecksum()
    {
        return $this->getData(self::CHECKSUM);
    }

    public function setChecksum($checksum)
    {
        return $this->setData(self::CHECKSUM, $checksum);
    }

    public function getFileSize()
    {
        return $this->getData(self::FILE_SIZE);
    }

    public function setFileSize($fileSize)
    {
        return $this->setData(self::FILE_SIZE, $fileSize);
    }

    public function getExportedCount()
    {
        return $this->getData(self::EXPORTED_COUNT);
    }

    public function setExportedCount($exportedCount)
    {
        return $this->setData(self::EXPORTED_COUNT, $exportedCount);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
