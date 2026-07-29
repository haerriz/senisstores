<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FeedProfileRepositoryInterface
{
    /**
     * Save feed profile.
     *
     * @param FeedProfileInterface $feedProfile
     * @return FeedProfileInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(FeedProfileInterface $feedProfile);

    /**
     * Retrieve feed profile by id.
     *
     * @param int $id
     * @return FeedProfileInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Retrieve feed profiles matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete feed profile.
     *
     * @param FeedProfileInterface $feedProfile
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(FeedProfileInterface $feedProfile);

    /**
     * Delete feed profile by ID.
     *
     * @param int $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
