<?php
namespace Haerriz\GoogleShoppingFeed\Model\Taxonomy;

use Haerriz\GoogleShoppingFeed\Api\CategoryMappingRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;

class CategoryMappingRepository implements CategoryMappingRepositoryInterface
{
    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function save(array $mappingData)
    {
        $magentoCategoryId = (int)($mappingData['magento_category_id'] ?? $mappingData['category_id'] ?? 0);
        $taxonomyPath = (string)($mappingData['taxonomy_path'] ?? $mappingData['full_path'] ?? '');

        if ($magentoCategoryId <= 0 || $taxonomyPath === '') {
            throw new CouldNotSaveException(__('Category mapping requires magento_category_id and taxonomy_path.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName('haerriz_google_shopping_feed_category_mapping');

        $connection->insertOnDuplicate(
            $table,
            [
                'magento_category_id' => $magentoCategoryId,
                'taxonomy_path' => $taxonomyPath,
            ],
            ['taxonomy_path']
        );

        return true;
    }
}
