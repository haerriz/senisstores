<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface FeedProfileInterface
{
    const PROFILE_ID = 'profile_id';
    const NAME = 'name';
    const STATUS = 'status';
    const STORE_ID = 'store_id';
    const FILENAME = 'filename';
    const FEED_TYPE = 'feed_type';
    const CONDITIONS_SERIALIZED = 'conditions_serialized';
    const ATTRIBUTES_MAPPING_SERIALIZED = 'attributes_mapping_serialized';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    const CURRENCY = 'currency';
    const DELIVERY_TYPE = 'delivery_type';
    const DELIVERY_HOST = 'delivery_host';
    const DELIVERY_PORT = 'delivery_port';
    const DELIVERY_USERNAME = 'delivery_username';
    const DELIVERY_PASSWORD = 'delivery_password';
    const DELIVERY_PATH = 'delivery_path';
    const CRON_EXPRESSION = 'cron_expression';
    const FREQUENCY = 'frequency';
    const TIMEZONE = 'timezone';
    const NEXT_RUN_AT = 'next_run_at';
    const CONCURRENCY_POLICY = 'concurrency_policy';
    const MAX_RETRIES = 'max_retries';
    const RETRY_COUNT = 'retry_count';
    const CONSECUTIVE_FAILURES = 'consecutive_failures';
    const IS_LOCKED = 'is_locked';
    const LOCKED_AT = 'locked_at';

    const UTM_ENABLED = 'utm_enabled';
    const UTM_SOURCE = 'utm_source';
    const UTM_MEDIUM = 'utm_medium';
    const UTM_CAMPAIGN = 'utm_campaign';
    const UTM_TERM = 'utm_term';
    const UTM_CONTENT = 'utm_content';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return string|null
     */
    public function getName();

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * @return int|null
     */
    public function getStatus();

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * @return int|null
     */
    public function getStoreId();

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId);

    /**
     * @return string|null
     */
    public function getCurrency();

    /**
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency);

    /**
     * @return string|null
     */
    public function getFilename();

    /**
     * @param string $filename
     * @return $this
     */
    public function setFilename($filename);

    /**
     * @return string|null
     */
    public function getFeedType();

    /**
     * @param string $feedType
     * @return $this
     */
    public function setFeedType($feedType);

    /**
     * @return string|null
     */
    public function getConditionsSerialized();

    /**
     * @param string $conditionsSerialized
     * @return $this
     */
    public function setConditionsSerialized($conditionsSerialized);

    /**
     * @return string|null
     */
    public function getAttributesMappingSerialized();

    /**
     * @param string $attributesMappingSerialized
     * @return $this
     */
    public function setAttributesMappingSerialized($attributesMappingSerialized);

    /**
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
    /**
     * @return string|null
     */
    public function getDeliveryType();

    /**
     * @param string $type
     * @return $this
     */
    public function setDeliveryType($type);

    /**
     * @return string|null
     */
    public function getDeliveryHost();

    /**
     * @param string $host
     * @return $this
     */
    public function setDeliveryHost($host);

    /**
     * @return int|null
     */
    public function getDeliveryPort();

    /**
     * @param int $port
     * @return $this
     */
    public function setDeliveryPort($port);

    /**
     * @return string|null
     */
    public function getDeliveryUsername();

    /**
     * @param string $username
     * @return $this
     */
    public function setDeliveryUsername($username);

    /**
     * @return string|null
     */
    public function getDeliveryPassword();

    /**
     * @param string $password
     * @return $this
     */
    public function setDeliveryPassword($password);

    /**
     * @return string|null
     */
    public function getDeliveryPath();

    /**
     * @param string $path
     * @return $this
     */
    public function setDeliveryPath($path);

    /**
     * @return string|null
     */
    public function getCronExpression();

    /**
     * @param string|null $cronExpression
     * @return $this
     */
    public function setCronExpression($cronExpression);

    /**
     * @return string|null
     */
    public function getFrequency();

    /**
     * @param string|null $frequency
     * @return $this
     */
    public function setFrequency($frequency);

    /**
     * @return string|null
     */
    public function getTimezone();

    /**
     * @param string|null $timezone
     * @return $this
     */
    public function setTimezone($timezone);

    /**
     * @return string|null
     */
    public function getNextRunAt();

    /**
     * @param string|null $nextRunAt
     * @return $this
     */
    public function setNextRunAt($nextRunAt);

    /**
     * @return string|null
     */
    public function getConcurrencyPolicy();

    /**
     * @param string $concurrencyPolicy
     * @return $this
     */
    public function setConcurrencyPolicy($concurrencyPolicy);

    /**
     * @return int
     */
    public function getMaxRetries();

    /**
     * @param int $maxRetries
     * @return $this
     */
    public function setMaxRetries($maxRetries);

    /**
     * @return int
     */
    public function getRetryCount();

    /**
     * @param int $retryCount
     * @return $this
     */
    public function setRetryCount($retryCount);

    /**
     * @return int
     */
    public function getConsecutiveFailures();

    /**
     * @param int $consecutiveFailures
     * @return $this
     */
    public function setConsecutiveFailures($consecutiveFailures);

    /**
     * @return int
     */
    public function getIsLocked();

    /**
     * @param int $isLocked
     * @return $this
     */
    public function setIsLocked($isLocked);

    /**
     * @return string|null
     */
    public function getLockedAt();

    /**
     * @param string|null $lockedAt
     * @return $this
     */
    public function setLockedAt($lockedAt);

    /**
     * @return int
     */
    public function getUtmEnabled();

    /**
     * @param int $utmEnabled
     * @return $this
     */
    public function setUtmEnabled($utmEnabled);

    /**
     * @return string|null
     */
    public function getUtmSource();

    /**
     * @param string|null $utmSource
     * @return $this
     */
    public function setUtmSource($utmSource);

    /**
     * @return string|null
     */
    public function getUtmMedium();

    /**
     * @param string|null $utmMedium
     * @return $this
     */
    public function setUtmMedium($utmMedium);

    /**
     * @return string|null
     */
    public function getUtmCampaign();

    /**
     * @param string|null $utmCampaign
     * @return $this
     */
    public function setUtmCampaign($utmCampaign);

    /**
     * @return string|null
     */
    public function getUtmTerm();

    /**
     * @param string|null $utmTerm
     * @return $this
     */
    public function setUtmTerm($utmTerm);

    /**
     * @return string|null
     */
    public function getUtmContent();

    /**
     * @param string|null $utmContent
     * @return $this
     */
    public function setUtmContent($utmContent);
}
