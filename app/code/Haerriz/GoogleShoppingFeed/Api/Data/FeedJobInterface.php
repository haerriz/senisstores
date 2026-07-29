<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface FeedJobInterface
{
    const JOB_ID = 'job_id';
    const PROFILE_ID = 'profile_id';
    const STATUS = 'status';
    const TOTAL_PRODUCTS = 'total_products';
    const PROCESSED_PRODUCTS = 'processed_products';
    const CREATED_AT = 'created_at';
    const STARTED_AT = 'started_at';
    const FINISHED_AT = 'finished_at';
    const TRIGGER_SOURCE = 'trigger_source';
    const SELECTED_COUNT = 'selected_count';
    const EXPORTED_COUNT = 'exported_count';
    const SKIPPED_COUNT = 'skipped_count';
    const WARNING_COUNT = 'warning_count';
    const ERROR_COUNT = 'error_count';
    const FILE_SIZE = 'file_size';
    const CHECKSUM = 'checksum';
    const DURATION = 'duration';
    const PEAK_MEMORY = 'peak_memory';
    const DELIVERY_RESULT = 'delivery_result';

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
     * @return string|null
     */
    public function getStatus();

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * @return int|null
     */
    public function getTotalProducts();

    /**
     * @param int $totalProducts
     * @return $this
     */
    public function setTotalProducts($totalProducts);

    /**
     * @return int|null
     */
    public function getProcessedProducts();

    /**
     * @param int $processedProducts
     * @return $this
     */
    public function setProcessedProducts($processedProducts);

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
    public function getStartedAt();

    /**
     * @param string $startedAt
     * @return $this
     */
    public function setStartedAt($startedAt);

    /**
     * @return string|null
     */
    public function getFinishedAt();

    /**
     * @param string $finishedAt
     * @return $this
     */
    public function setFinishedAt($finishedAt);

    /**
     * @return string|null
     */
    public function getTriggerSource();

    /**
     * @param string $triggerSource
     * @return $this
     */
    public function setTriggerSource($triggerSource);

    /**
     * @return int
     */
    public function getSelectedCount();

    /**
     * @param int $selectedCount
     * @return $this
     */
    public function setSelectedCount($selectedCount);

    /**
     * @return int
     */
    public function getExportedCount();

    /**
     * @param int $exportedCount
     * @return $this
     */
    public function setExportedCount($exportedCount);

    /**
     * @return int
     */
    public function getSkippedCount();

    /**
     * @param int $skippedCount
     * @return $this
     */
    public function setSkippedCount($skippedCount);

    /**
     * @return int
     */
    public function getWarningCount();

    /**
     * @param int $warningCount
     * @return $this
     */
    public function setWarningCount($warningCount);

    /**
     * @return int
     */
    public function getErrorCount();

    /**
     * @param int $errorCount
     * @return $this
     */
    public function setErrorCount($errorCount);

    /**
     * @return int
     */
    public function getFileSize();

    /**
     * @param int $fileSize
     * @return $this
     */
    public function setFileSize($fileSize);

    /**
     * @return string|null
     */
    public function getChecksum();

    /**
     * @param string|null $checksum
     * @return $this
     */
    public function setChecksum($checksum);

    /**
     * @return float
     */
    public function getDuration();

    /**
     * @param float $duration
     * @return $this
     */
    public function setDuration($duration);

    /**
     * @return int
     */
    public function getPeakMemory();

    /**
     * @param int $peakMemory
     * @return $this
     */
    public function setPeakMemory($peakMemory);

    /**
     * @return string|null
     */
    public function getDeliveryResult();

    /**
     * @param string|null $deliveryResult
     * @return $this
     */
    public function setDeliveryResult($deliveryResult);
}
