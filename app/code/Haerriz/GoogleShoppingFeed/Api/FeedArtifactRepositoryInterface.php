<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedArtifactInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FeedArtifactRepositoryInterface
{
    /**
     * @param FeedArtifactInterface $artifact
     * @return FeedArtifactInterface
     */
    public function save(FeedArtifactInterface $artifact);

    /**
     * @param int $id
     * @return FeedArtifactInterface
     */
    public function getById(int $id);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
