<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedRemoteStateInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedRemoteStateInterface as FeedRemoteStateApiInterface;
use Magento\Framework\Model\AbstractModel;

class FeedRemoteState extends AbstractModel implements FeedRemoteStateInterface, FeedRemoteStateApiInterface
{
    protected function _construct()
    {
        $this->_init(\Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedRemoteState::class);
    }

    public function getId()
    {
        return $this->getData(self::STATE_ID);
    }

    public function setId($id)
    {
        return $this->setData(self::STATE_ID, $id);
    }

    public function getProfileId()
    {
        return $this->getData(self::PROFILE_ID) !== null ? (int)$this->getData(self::PROFILE_ID) : null;
    }

    public function setProfileId($profileId)
    {
        return $this->setData(self::PROFILE_ID, (int)$profileId);
    }

    public function getProductId()
    {
        return (int)$this->getData(self::PRODUCT_ID);
    }

    public function setProductId($productId)
    {
        return $this->setData(self::PRODUCT_ID, (int)$productId);
    }

    public function getOfferId()
    {
        return $this->getData(self::OFFER_ID);
    }

    public function setOfferId($offerId)
    {
        return $this->setData(self::OFFER_ID, (string)$offerId);
    }

    public function getSyncStatus()
    {
        return $this->getData(self::SYNC_STATUS);
    }

    public function setSyncStatus($syncStatus)
    {
        return $this->setData(self::SYNC_STATUS, (string)$syncStatus);
    }

    public function getRemoteStatus(): string
    {
        return (string)($this->getSyncStatus() ?: 'unknown');
    }

    public function setRemoteStatus(string $status)
    {
        return $this->setSyncStatus($status);
    }

    public function getPayloadHash()
    {
        return $this->getData(self::PAYLOAD_HASH);
    }

    public function setPayloadHash($payloadHash)
    {
        return $this->setData(self::PAYLOAD_HASH, $payloadHash);
    }

    public function getIssues()
    {
        return $this->getData(self::ISSUES);
    }

    public function setIssues($issues)
    {
        return $this->setData(self::ISSUES, $issues);
    }

    public function getSyncedAt()
    {
        return $this->getData(self::SYNCED_AT);
    }

    public function setSyncedAt($syncedAt)
    {
        return $this->setData(self::SYNCED_AT, $syncedAt);
    }

    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
