<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface FeedRemoteStateInterface
{
    public const STATE_ID = 'state_id';
    public const PROFILE_ID = 'profile_id';
    public const PRODUCT_ID = 'product_id';
    public const OFFER_ID = 'offer_id';
    public const SYNC_STATUS = 'sync_status';
    public const PAYLOAD_HASH = 'payload_hash';
    public const ISSUES = 'issues';
    public const SYNCED_AT = 'synced_at';
    public const UPDATED_AT = 'updated_at';

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
     * @return int|null
     */
    public function getProfileId();

    /**
     * @param int $profileId
     * @return $this
     */
    public function setProfileId($profileId);

    /**
     * @return int
     */
    public function getProductId();

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId($productId);

    /**
     * @return string|null
     */
    public function getOfferId();

    /**
     * @param string $offerId
     * @return $this
     */
    public function setOfferId($offerId);

    /**
     * @return string|null
     */
    public function getSyncStatus();

    /**
     * @param string $syncStatus
     * @return $this
     */
    public function setSyncStatus($syncStatus);

    /**
     * Alias used by Merchant reconciliation.
     *
     * @return string
     */
    public function getRemoteStatus(): string;

    /**
     * Alias used by Merchant reconciliation.
     *
     * @param string $status
     * @return $this
     */
    public function setRemoteStatus(string $status);

    /**
     * @return string|null
     */
    public function getPayloadHash();

    /**
     * @param string|null $payloadHash
     * @return $this
     */
    public function setPayloadHash($payloadHash);

    /**
     * @return string|null
     */
    public function getIssues();

    /**
     * @param string|null $issues
     * @return $this
     */
    public function setIssues($issues);

    /**
     * @return string|null
     */
    public function getSyncedAt();

    /**
     * @param string|null $syncedAt
     * @return $this
     */
    public function setSyncedAt($syncedAt);

    /**
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * @param string|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
}
