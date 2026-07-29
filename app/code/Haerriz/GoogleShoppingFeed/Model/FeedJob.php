<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Framework\Model\AbstractModel;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedJobInterface;

class FeedJob extends AbstractModel implements FeedJobInterface
{
    protected function _construct()
    {
        $this->_init(\Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob::class);
    }

    public function getId()
    {
        return $this->getData(self::JOB_ID);
    }

    public function setId($id)
    {
        return $this->setData(self::JOB_ID, $id);
    }

    public function getProfileId()
    {
        return $this->getData(self::PROFILE_ID);
    }

    public function setProfileId($profileId)
    {
        return $this->setData(self::PROFILE_ID, $profileId);
    }

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getTotalProducts()
    {
        return $this->getData(self::TOTAL_PRODUCTS);
    }

    public function setTotalProducts($totalProducts)
    {
        return $this->setData(self::TOTAL_PRODUCTS, $totalProducts);
    }

    public function getProcessedProducts()
    {
        return $this->getData(self::PROCESSED_PRODUCTS);
    }

    public function setProcessedProducts($processedProducts)
    {
        return $this->setData(self::PROCESSED_PRODUCTS, $processedProducts);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getStartedAt()
    {
        return $this->getData(self::STARTED_AT);
    }

    public function setStartedAt($startedAt)
    {
        return $this->setData(self::STARTED_AT, $startedAt);
    }

    public function getFinishedAt()
    {
        return $this->getData(self::FINISHED_AT);
    }

    public function setFinishedAt($finishedAt)
    {
        return $this->setData(self::FINISHED_AT, $finishedAt);
    }

    public function getTriggerSource()
    {
        return $this->getData(self::TRIGGER_SOURCE);
    }

    public function setTriggerSource($triggerSource)
    {
        return $this->setData(self::TRIGGER_SOURCE, $triggerSource);
    }

    public function getSelectedCount()
    {
        return (int)$this->getData(self::SELECTED_COUNT);
    }

    public function setSelectedCount($selectedCount)
    {
        return $this->setData(self::SELECTED_COUNT, $selectedCount);
    }

    public function getExportedCount()
    {
        return (int)$this->getData(self::EXPORTED_COUNT);
    }

    public function setExportedCount($exportedCount)
    {
        return $this->setData(self::EXPORTED_COUNT, $exportedCount);
    }

    public function getSkippedCount()
    {
        return (int)$this->getData(self::SKIPPED_COUNT);
    }

    public function setSkippedCount($skippedCount)
    {
        return $this->setData(self::SKIPPED_COUNT, $skippedCount);
    }

    public function getWarningCount()
    {
        return (int)$this->getData(self::WARNING_COUNT);
    }

    public function setWarningCount($warningCount)
    {
        return $this->setData(self::WARNING_COUNT, $warningCount);
    }

    public function getErrorCount()
    {
        return (int)$this->getData(self::ERROR_COUNT);
    }

    public function setErrorCount($errorCount)
    {
        return $this->setData(self::ERROR_COUNT, $errorCount);
    }

    public function getFileSize()
    {
        return (int)$this->getData(self::FILE_SIZE);
    }

    public function setFileSize($fileSize)
    {
        return $this->setData(self::FILE_SIZE, $fileSize);
    }

    public function getChecksum()
    {
        return $this->getData(self::CHECKSUM);
    }

    public function setChecksum($checksum)
    {
        return $this->setData(self::CHECKSUM, $checksum);
    }

    public function getDuration()
    {
        return (float)$this->getData(self::DURATION);
    }

    public function setDuration($duration)
    {
        return $this->setData(self::DURATION, $duration);
    }

    public function getPeakMemory()
    {
        return (int)$this->getData(self::PEAK_MEMORY);
    }

    public function setPeakMemory($peakMemory)
    {
        return $this->setData(self::PEAK_MEMORY, $peakMemory);
    }

    public function getDeliveryResult()
    {
        return $this->getData(self::DELIVERY_RESULT);
    }

    public function setDeliveryResult($deliveryResult)
    {
        return $this->setData(self::DELIVERY_RESULT, $deliveryResult);
    }
}
