<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface FeedProfileSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get feed profiles list.
     *
     * @return \Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface[]
     */
    public function getItems();

    /**
     * Set feed profiles list.
     *
     * @param \Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
