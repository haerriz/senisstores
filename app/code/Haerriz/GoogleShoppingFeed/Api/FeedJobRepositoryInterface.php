<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedJobInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FeedJobRepositoryInterface
{
    /**
     * Save feed job.
     *
     * @param FeedJobInterface $feedJob
     * @return FeedJobInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(FeedJobInterface $feedJob);

    /**
     * Retrieve feed job by id.
     *
     * @param int $id
     * @return FeedJobInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Retrieve feed jobs matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Haerriz\GoogleShoppingFeed\Api\Data\FeedJobSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete feed job.
     *
     * @param FeedJobInterface $feedJob
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(FeedJobInterface $feedJob);

    /**
     * Delete feed job by ID.
     *
     * @param int $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
