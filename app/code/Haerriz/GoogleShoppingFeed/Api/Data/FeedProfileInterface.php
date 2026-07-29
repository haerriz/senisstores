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

    public function getId();
    public function setId($id);
    public function getName();
    public function setName($name);
    public function getStatus();
    public function setStatus($status);
    public function getStoreId();
    public function setStoreId($storeId);
    public function getFilename();
    public function setFilename($filename);
    public function getFeedType();
    public function setFeedType($feedType);
    public function getConditionsSerialized();
    public function setConditionsSerialized($conditionsSerialized);
    public function getAttributesMappingSerialized();
    public function setAttributesMappingSerialized($attributesMappingSerialized);
    public function getCreatedAt();
    public function setCreatedAt($createdAt);
    public function getUpdatedAt();
    public function setUpdatedAt($updatedAt);
}
