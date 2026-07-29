<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface FeedLogSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get feed logs list.
     *
     * @return \Haerriz\GoogleShoppingFeed\Api\Data\FeedLogInterface[]
     */
    public function getItems();

    /**
     * Set feed logs list.
     *
     * @param \Haerriz\GoogleShoppingFeed\Api\Data\FeedLogInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
