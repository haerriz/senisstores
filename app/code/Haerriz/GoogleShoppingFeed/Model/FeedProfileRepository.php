<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile as ResourceModel;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class FeedProfileRepository implements FeedProfileRepositoryInterface
{
    protected $resource;
    protected $feedProfileFactory;
    protected $collectionFactory;
    protected $searchResultsFactory;
    protected $collectionProcessor;

    public function __construct(
        ResourceModel $resource,
        FeedProfileFactory $feedProfileFactory,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->feedProfileFactory = $feedProfileFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    public function save(FeedProfileInterface $feedProfile)
    {
        try {
            $this->resource->save($feedProfile);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $feedProfile;
    }

    public function getById($id)
    {
        $feedProfile = $this->feedProfileFactory->create();
        $this->resource->load($feedProfile, $id);
        if (!$feedProfile->getId()) {
            throw new NoSuchEntityException(__('The feed profile with the "%1" ID doesn\'t exist.', $id));
        }
        return $feedProfile;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function delete(FeedProfileInterface $feedProfile)
    {
        try {
            $this->resource->delete($feedProfile);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    public function deleteById($id)
    {
        return $this->delete($this->getById($id));
    }
}
