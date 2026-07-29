<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedLogInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedLogSearchResultsInterfaceFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedLogRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog as ResourceLog;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedLog\CollectionFactory as LogCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class FeedLogRepository implements FeedLogRepositoryInterface
{
    /**
     * @var ResourceLog
     */
    protected $resource;

    /**
     * @var FeedLogFactory
     */
    protected $logFactory;

    /**
     * @var LogCollectionFactory
     */
    protected $logCollectionFactory;

    /**
     * @var FeedLogSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @param ResourceLog $resource
     * @param FeedLogFactory $logFactory
     * @param LogCollectionFactory $logCollectionFactory
     * @param FeedLogSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceLog $resource,
        FeedLogFactory $logFactory,
        LogCollectionFactory $logCollectionFactory,
        FeedLogSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->logFactory = $logFactory;
        $this->logCollectionFactory = $logCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritdoc
     */
    public function save(FeedLogInterface $feedLog)
    {
        try {
            $this->resource->save($feedLog);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the feed log: %1', $exception->getMessage()),
                $exception
            );
        }
        return $feedLog;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $log = $this->logFactory->create();
        $this->resource->load($log, $id);
        if (!$log->getId()) {
            throw new NoSuchEntityException(__('Feed log with id "%1" does not exist.', $id));
        }
        return $log;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->logCollectionFactory->create();
        
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
    public function delete(FeedLogInterface $feedLog)
    {
        try {
            $this->resource->delete($feedLog);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the feed log: %1', $exception->getMessage())
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
