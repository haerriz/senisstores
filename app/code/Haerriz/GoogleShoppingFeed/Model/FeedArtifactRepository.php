<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedArtifactInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedArtifactRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact as ResourceModel;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedArtifact\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class FeedArtifactRepository implements FeedArtifactRepositoryInterface
{
    private ResourceModel $resource;
    private FeedArtifactFactory $factory;
    private CollectionFactory $collectionFactory;
    private CollectionProcessorInterface $collectionProcessor;
    private SearchResultsInterfaceFactory $searchResultsFactory;

    public function __construct(
        ResourceModel $resource,
        FeedArtifactFactory $factory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->factory = $factory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(FeedArtifactInterface $artifact)
    {
        try {
            $this->resource->save($artifact);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save feed artifact: %1', $e->getMessage()), $e);
        }
        return $artifact;
    }

    public function getById(int $id)
    {
        $artifact = $this->factory->create();
        $this->resource->load($artifact, $id);
        if (!$artifact->getId()) {
            throw new NoSuchEntityException(__('Feed artifact with id "%1" does not exist.', $id));
        }
        return $artifact;
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
}
