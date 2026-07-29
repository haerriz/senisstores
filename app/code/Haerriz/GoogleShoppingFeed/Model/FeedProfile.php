<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Framework\Model\AbstractModel;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

class FeedProfile extends AbstractModel implements FeedProfileInterface
{
    protected function _construct()
    {
        $this->_init(\Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile::class);
    }

    public function getId()
    {
        return $this->getData(self::PROFILE_ID);
    }

    public function setId($id)
    {
        return $this->setData(self::PROFILE_ID, $id);
    }

    public function getName()
    {
        return $this->getData(self::NAME);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getCurrency()
    {
        return $this->getData(self::CURRENCY);
    }

    public function setCurrency($currency)
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    public function getFilename()
    {
        return $this->getData(self::FILENAME);
    }

    public function setFilename($filename)
    {
        return $this->setData(self::FILENAME, $filename);
    }

    public function getFeedType()
    {
        return $this->getData(self::FEED_TYPE);
    }

    public function setFeedType($feedType)
    {
        return $this->setData(self::FEED_TYPE, $feedType);
    }

    public function getConditionsSerialized()
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    public function setConditionsSerialized($conditionsSerialized)
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditionsSerialized);
    }

    public function getAttributesMappingSerialized()
    {
        return $this->getData(self::ATTRIBUTES_MAPPING_SERIALIZED);
    }

    public function setAttributesMappingSerialized($attributesMappingSerialized)
    {
        return $this->setData(self::ATTRIBUTES_MAPPING_SERIALIZED, $attributesMappingSerialized);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
    public function getDeliveryType()
    {
        return $this->getData(self::DELIVERY_TYPE);
    }

    public function setDeliveryType($type)
    {
        return $this->setData(self::DELIVERY_TYPE, $type);
    }

    public function getDeliveryHost()
    {
        return $this->getData(self::DELIVERY_HOST);
    }

    public function setDeliveryHost($host)
    {
        return $this->setData(self::DELIVERY_HOST, $host);
    }

    public function getDeliveryPort()
    {
        return $this->getData(self::DELIVERY_PORT);
    }

    public function setDeliveryPort($port)
    {
        return $this->setData(self::DELIVERY_PORT, $port);
    }

    public function getDeliveryUsername()
    {
        return $this->getData(self::DELIVERY_USERNAME);
    }

    public function setDeliveryUsername($username)
    {
        return $this->setData(self::DELIVERY_USERNAME, $username);
    }

    public function getDeliveryPassword()
    {
        return $this->getData(self::DELIVERY_PASSWORD);
    }

    public function setDeliveryPassword($password)
    {
        return $this->setData(self::DELIVERY_PASSWORD, $password);
    }

    public function getDeliveryPath()
    {
        return $this->getData(self::DELIVERY_PATH);
    }

    public function setDeliveryPath($path)
    {
        return $this->setData(self::DELIVERY_PATH, $path);
    }

    public function getCronExpression()
    {
        return $this->getData(self::CRON_EXPRESSION);
    }

    public function setCronExpression($cronExpression)
    {
        return $this->setData(self::CRON_EXPRESSION, $cronExpression);
    }

    public function getFrequency()
    {
        return $this->getData(self::FREQUENCY);
    }

    public function setFrequency($frequency)
    {
        return $this->setData(self::FREQUENCY, $frequency);
    }

    public function getTimezone()
    {
        return $this->getData(self::TIMEZONE);
    }

    public function setTimezone($timezone)
    {
        return $this->setData(self::TIMEZONE, $timezone);
    }

    public function getNextRunAt()
    {
        return $this->getData(self::NEXT_RUN_AT);
    }

    public function setNextRunAt($nextRunAt)
    {
        return $this->setData(self::NEXT_RUN_AT, $nextRunAt);
    }

    public function getConcurrencyPolicy()
    {
        return $this->getData(self::CONCURRENCY_POLICY);
    }

    public function setConcurrencyPolicy($concurrencyPolicy)
    {
        return $this->setData(self::CONCURRENCY_POLICY, $concurrencyPolicy);
    }

    public function getMaxRetries()
    {
        return (int)$this->getData(self::MAX_RETRIES);
    }

    public function setMaxRetries($maxRetries)
    {
        return $this->setData(self::MAX_RETRIES, $maxRetries);
    }

    public function getRetryCount()
    {
        return (int)$this->getData(self::RETRY_COUNT);
    }

    public function setRetryCount($retryCount)
    {
        return $this->setData(self::RETRY_COUNT, $retryCount);
    }

    public function getConsecutiveFailures()
    {
        return (int)$this->getData(self::CONSECUTIVE_FAILURES);
    }

    public function setConsecutiveFailures($consecutiveFailures)
    {
        return $this->setData(self::CONSECUTIVE_FAILURES, $consecutiveFailures);
    }

    public function getIsLocked()
    {
        return (int)$this->getData(self::IS_LOCKED);
    }

    public function setIsLocked($isLocked)
    {
        return $this->setData(self::IS_LOCKED, $isLocked);
    }

    public function getLockedAt()
    {
        return $this->getData(self::LOCKED_AT);
    }

    public function setLockedAt($lockedAt)
    {
        return $this->setData(self::LOCKED_AT, $lockedAt);
    }

    public function getUtmEnabled()
    {
        return (int)$this->getData(self::UTM_ENABLED);
    }

    public function setUtmEnabled($utmEnabled)
    {
        return $this->setData(self::UTM_ENABLED, $utmEnabled);
    }

    public function getUtmSource()
    {
        return $this->getData(self::UTM_SOURCE);
    }

    public function setUtmSource($utmSource)
    {
        return $this->setData(self::UTM_SOURCE, $utmSource);
    }

    public function getUtmMedium()
    {
        return $this->getData(self::UTM_MEDIUM);
    }

    public function setUtmMedium($utmMedium)
    {
        return $this->setData(self::UTM_MEDIUM, $utmMedium);
    }

    public function getUtmCampaign()
    {
        return $this->getData(self::UTM_CAMPAIGN);
    }

    public function setUtmCampaign($utmCampaign)
    {
        return $this->setData(self::UTM_CAMPAIGN, $utmCampaign);
    }

    public function getUtmTerm()
    {
        return $this->getData(self::UTM_TERM);
    }

    public function setUtmTerm($utmTerm)
    {
        return $this->setData(self::UTM_TERM, $utmTerm);
    }

    public function getUtmContent()
    {
        return $this->getData(self::UTM_CONTENT);
    }

    public function setUtmContent($utmContent)
    {
        return $this->setData(self::UTM_CONTENT, $utmContent);
    }
}
