<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface FeedLogInterface
{
    const LOG_ID = 'log_id';
    const PROFILE_ID = 'profile_id';
    const JOB_ID = 'job_id';
    const TYPE = 'type';
    const MESSAGE = 'message';
    const CREATED_AT = 'created_at';

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
     * @return int|null
     */
    public function getJobId();

    /**
     * @param int $jobId
     * @return $this
     */
    public function setJobId($jobId);

    /**
     * @return string|null
     */
    public function getType();

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type);

    /**
     * @return string|null
     */
    public function getMessage();

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage($message);

    /**
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);
}
