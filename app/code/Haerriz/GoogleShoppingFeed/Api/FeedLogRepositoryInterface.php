<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedLogInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FeedLogRepositoryInterface
{
    /**
     * Save feed log.
     *
     * @param FeedLogInterface $feedLog
     * @return FeedLogInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(FeedLogInterface $feedLog);

    /**
     * Retrieve feed log by id.
     *
     * @param int $id
     * @return FeedLogInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Retrieve feed logs matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Haerriz\GoogleShoppingFeed\Api\Data\FeedLogSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete feed log.
     *
     * @param FeedLogInterface $feedLog
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(FeedLogInterface $feedLog);

    /**
     * Delete feed log by ID.
     *
     * @param int $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
