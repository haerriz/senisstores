<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Job;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedJobInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\Logger\Sanitizer;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class View extends Template
{
    private $jobRepository;
    private $logCollectionFactory;
    private $sanitizer;
    private $job;

    public function __construct(
        Context $context,
        FeedJobRepositoryInterface $jobRepository,
        CollectionFactory $logCollectionFactory,
        Sanitizer $sanitizer,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->jobRepository = $jobRepository;
        $this->logCollectionFactory = $logCollectionFactory;
        $this->sanitizer = $sanitizer;
    }

    public function getJob()
    {
        if (!$this->job) {
            $this->job = $this->jobRepository->getById((int)$this->getRequest()->getParam('id'));
        }
        return $this->job;
    }

    public function getDetails()
    {
        $job = $this->getJob();
        $fields = [
            'Status' => FeedJobInterface::STATUS,
            'Profile ID' => FeedJobInterface::PROFILE_ID,
            'Source' => FeedJobInterface::TRIGGER_SOURCE,
            'Selected' => FeedJobInterface::SELECTED_COUNT,
            'Processed' => FeedJobInterface::PROCESSED_PRODUCTS,
            'Exported' => FeedJobInterface::EXPORTED_COUNT,
            'Skipped' => FeedJobInterface::SKIPPED_COUNT,
            'Warnings' => FeedJobInterface::WARNING_COUNT,
            'Errors' => FeedJobInterface::ERROR_COUNT,
            'File Size (bytes)' => FeedJobInterface::FILE_SIZE,
            'SHA-256' => FeedJobInterface::CHECKSUM,
            'Duration (seconds)' => FeedJobInterface::DURATION,
            'Peak Memory (bytes)' => FeedJobInterface::PEAK_MEMORY,
            'Delivery Result' => FeedJobInterface::DELIVERY_RESULT,
            'Created At' => FeedJobInterface::CREATED_AT,
            'Started At' => FeedJobInterface::STARTED_AT,
            'Finished At' => FeedJobInterface::FINISHED_AT,
            'Failure Category' => 'failure_category',
            'Failure Message' => 'failure_message',
            'Correlation ID' => 'correlation_id',
            'Format' => 'format',
        ];
        $details = [];
        foreach ($fields as $label => $key) {
            $details[$label] = $this->sanitizer->sanitize((string)$job->getData($key));
        }
        return $details;
    }

    public function getLogs()
    {
        $collection = $this->logCollectionFactory->create();
        $collection->addFieldToFilter('job_id', (int)$this->getJob()->getId());
        $collection->setOrder('log_id', 'ASC');
        return $collection;
    }

    public function sanitize($message)
    {
        return $this->sanitizer->sanitize((string)$message);
    }

    public function getBackUrl()
    {
        return $this->getUrl('*/*/index');
    }

    public function getDownloadUrl()
    {
        return $this->getUrl('*/*/download', ['id' => (int)$this->getJob()->getId()]);
    }
}
