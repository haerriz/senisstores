<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileSearchResultsInterfaceFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile as ResourceProfile;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory as ProfileCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class FeedProfileRepository implements FeedProfileRepositoryInterface
{
    /**
     * @var ResourceProfile
     */
    protected $resource;

    /**
     * @var FeedProfileFactory
     */
    protected $profileFactory;

    /**
     * @var ProfileCollectionFactory
     */
    protected $profileCollectionFactory;

    /**
     * @var FeedProfileSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @param ResourceProfile $resource
     * @param FeedProfileFactory $profileFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param FeedProfileSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceProfile $resource,
        FeedProfileFactory $profileFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        FeedProfileSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->profileFactory = $profileFactory;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritdoc
     */
    public function save(FeedProfileInterface $feedProfile)
    {
        try {
            $this->resource->save($feedProfile);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the feed profile: %1', $exception->getMessage()),
                $exception
            );
        }
        return $feedProfile;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $profile = $this->profileFactory->create();
        $this->resource->load($profile, $id);
        if (!$profile->getId()) {
            throw new NoSuchEntityException(__('Feed profile with id "%1" does not exist.', $id));
        }
        return $profile;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->profileCollectionFactory->create();
        
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
    public function delete(FeedProfileInterface $feedProfile)
    {
        try {
            $this->resource->delete($feedProfile);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the feed profile: %1', $exception->getMessage())
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
