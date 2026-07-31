<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Magento\Framework\App\ResourceConnection;

class CategoryIdResolver
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Resolve selected categories and, optionally, all descendants in one query.
     *
     * @param int[] $categoryIds
     * @param bool $includeDescendants
     * @return int[]
     */
    public function resolve(array $categoryIds, $includeDescendants)
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if (!$categoryIds || !$includeDescendants) {
            return $categoryIds;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('catalog_category_entity'), ['entity_id'])
            ->where('entity_id IN (?)', $categoryIds);
        $pattern = '(^|/)(' . implode('|', $categoryIds) . ')(/|$)';
        $select->orWhere('path REGEXP ?', $pattern);

        return array_map('intval', $connection->fetchCol($select));
    }
}
