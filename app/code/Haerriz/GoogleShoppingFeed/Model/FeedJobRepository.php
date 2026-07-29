<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedJobInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedJobSearchResultsInterfaceFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob as ResourceJob;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob\CollectionFactory as JobCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class FeedJobRepository implements FeedJobRepositoryInterface
{
    /**
     * @var ResourceJob
     */
    protected $resource;

    /**
     * @var FeedJobFactory
     */
    protected $jobFactory;

    /**
     * @var JobCollectionFactory
     */
    protected $jobCollectionFactory;

    /**
     * @var FeedJobSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @param ResourceJob $resource
     * @param FeedJobFactory $jobFactory
     * @param JobCollectionFactory $jobCollectionFactory
     * @param FeedJobSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceJob $resource,
        FeedJobFactory $jobFactory,
        JobCollectionFactory $jobCollectionFactory,
        FeedJobSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->jobFactory = $jobFactory;
        $this->jobCollectionFactory = $jobCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritdoc
     */
    public function save(FeedJobInterface $feedJob)
    {
        try {
            $this->resource->save($feedJob);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the feed job: %1', $exception->getMessage()),
                $exception
            );
        }
        return $feedJob;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $job = $this->jobFactory->create();
        $this->resource->load($job, $id);
        if (!$job->getId()) {
            throw new NoSuchEntityException(__('Feed job with id "%1" does not exist.', $id));
        }
        return $job;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->jobCollectionFactory->create();
        
        $this->collectionProcessor->process($searchCriteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        
        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }
        
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(FeedJobInterface $feedJob)
    {
        try {
            $this->resource->delete($feedJob);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the feed job: %1', $exception->getMessage())
            );
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById($id)
    {
        return $this->delete($this->getById($id));
    }
}
