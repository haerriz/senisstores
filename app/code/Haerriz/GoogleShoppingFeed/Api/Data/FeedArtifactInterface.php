<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface FeedArtifactInterface
{
    public const ARTIFACT_ID = 'artifact_id';
    public const PROFILE_ID = 'profile_id';
    public const JOB_ID = 'job_id';
    public const FILE_PATH = 'file_path';
    public const CHECKSUM = 'checksum';
    public const FILE_SIZE = 'file_size';
    public const EXPORTED_COUNT = 'exported_count';
    public const CREATED_AT = 'created_at';

    public function getId();
    public function setId($id);

    public function getProfileId();
    public function setProfileId($profileId);

    public function getJobId();
    public function setJobId($jobId);

    public function getFilePath();
    public function setFilePath($filePath);

    public function getChecksum();
    public function setChecksum($checksum);

    public function getFileSize();
    public function setFileSize($fileSize);

    public function getExportedCount();
    public function setExportedCount($exportedCount);

    public function getCreatedAt();
    public function setCreatedAt($createdAt);
}
