<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Framework\Model\AbstractModel;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedLogInterface;

class FeedLog extends AbstractModel implements FeedLogInterface
{
    protected function _construct()
    {
        $this->_init(\Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog::class);
    }

    public function getId()
    {
        return $this->getData(self::LOG_ID);
    }

    public function setId($id)
    {
        return $this->setData(self::LOG_ID, $id);
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

    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    public function getMessage()
    {
        return $this->getData(self::MESSAGE);
    }

    public function setMessage($message)
    {
        return $this->setData(self::MESSAGE, $message);
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
