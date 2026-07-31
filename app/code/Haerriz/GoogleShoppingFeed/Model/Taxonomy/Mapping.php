<?php
namespace Haerriz\GoogleShoppingFeed\Model\Taxonomy;

use Haerriz\GoogleShoppingFeed\Api\TaxonomyRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class Mapping
{
    private $connection;
    private $taxonomyRepository;
    private $logger;
    private array $cache = [];

    public function __construct(
        ResourceConnection $resourceConnection,
        TaxonomyRepositoryInterface $taxonomyRepository,
        LoggerInterface $logger
    ) {
        $this->connection         = $resourceConnection->getConnection();
        $this->taxonomyRepository = $taxonomyRepository;
        $this->logger             = $logger;
    }

    /**
     * Resolve a Magento category ID to a Google taxonomy path.
     * FIX 19: Uses TaxonomyRepositoryInterface::search() for keyword fallback.
     */
    public function resolveCategoryPath(int $categoryId): string
    {
        if (isset($this->cache[$categoryId])) {
            return $this->cache[$categoryId];
        }

        // 1. Try the category mapping table first
        $result = null;
        try {
            $mappingTable = $this->connection->getTableName('haerriz_google_shopping_feed_category_mapping');
            $result = $this->connection->fetchOne(
                "SELECT taxonomy_path FROM {$mappingTable} WHERE category_id = :id LIMIT 1",
                [':id' => $categoryId]
            );
        } catch (\Exception $e) {
            $this->logger->debug("Taxonomy\Mapping: category_mapping table lookup failed: " . $e->getMessage());
        }

        // 2. If no direct mapping, get the category name and use TaxonomyRepositoryInterface::search()
        if (!$result) {
            try {
                $catTable    = $this->connection->getTableName('catalog_category_entity_varchar');
                $eavTable    = $this->connection->getTableName('eav_attribute');
                $categoryName = $this->connection->fetchOne(
                    "SELECT val.value FROM {$catTable} val
                     JOIN {$eavTable} ea ON ea.attribute_id = val.attribute_id
                     WHERE ea.attribute_code = 'name' AND val.entity_id = :id
                     LIMIT 1",
                    [':id' => $categoryId]
                );

                if ($categoryName) {
                    $matches = $this->taxonomyRepository->search((string)$categoryName);
                    if (!empty($matches)) {
                        $result = is_array($matches[0]) ? ($matches[0]['path'] ?? $matches[0]['taxonomy_path'] ?? null) : (string)$matches[0];
                        $this->logger->debug("Taxonomy\Mapping: Resolved [{$categoryName}] via search to [{$result}]");
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug("Taxonomy\Mapping: taxonomy search failed: " . $e->getMessage());
            }
        }

        $resolved = $result ?: 'Apparel & Accessories';
        $this->cache[$categoryId] = $resolved;
        $this->logger->debug("Taxonomy\Mapping: category_id={$categoryId} -> [{$resolved}]");
        return $resolved;
    }

    /**
     * Add or update a category -> taxonomy mapping (persisted to DB).
     */
    public function setMapping(int $categoryId, string $taxonomyPath): void
    {
        try {
            $table = $this->connection->getTableName('haerriz_google_shopping_feed_category_mapping');
            $this->connection->insertOnDuplicate($table, [
                'category_id'   => $categoryId,
                'taxonomy_path' => $taxonomyPath,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            $this->cache[$categoryId] = $taxonomyPath;
            $this->logger->info("Taxonomy\Mapping: Saved mapping category_id={$categoryId} -> [{$taxonomyPath}]");
        } catch (\Exception $e) {
            $this->logger->warning("Taxonomy\Mapping: setMapping failed: " . $e->getMessage());
        }
    }
}
