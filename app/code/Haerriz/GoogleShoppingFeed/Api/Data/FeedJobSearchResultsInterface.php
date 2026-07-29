<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface FeedJobSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get feed jobs list.
     *
     * @return \Haerriz\GoogleShoppingFeed\Api\Data\FeedJobInterface[]
     */
    public function getItems();

    /**
     * Set feed jobs list.
     *
     * @param \Haerriz\GoogleShoppingFeed\Api\Data\FeedJobInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
