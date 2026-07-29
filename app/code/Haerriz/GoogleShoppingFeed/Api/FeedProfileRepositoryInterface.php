<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FeedProfileRepositoryInterface
{
    public function save(FeedProfileInterface $feedProfile);
    public function getById($id);
    public function getList(SearchCriteriaInterface $searchCriteria);
    public function delete(FeedProfileInterface $feedProfile);
    public function deleteById($id);
}
