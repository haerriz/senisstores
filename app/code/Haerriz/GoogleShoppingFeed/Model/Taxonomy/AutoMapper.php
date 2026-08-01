<?php
namespace Haerriz\GoogleShoppingFeed\Model\Taxonomy;

use Haerriz\GoogleShoppingFeed\Api\CategoryMappingRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class AutoMapper
{
    private CategoryCollectionFactory $categoryCollectionFactory;
    private ResourceConnection $resourceConnection;
    private CategoryMappingRepositoryInterface $categoryMappingRepository;
    private LoggerInterface $logger;

    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        ResourceConnection $resourceConnection,
        CategoryMappingRepositoryInterface $categoryMappingRepository,
        LoggerInterface $logger
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->categoryMappingRepository = $categoryMappingRepository;
        $this->logger = $logger;
    }

    /**
     * Map Magento categories to the closest Google taxonomy full_path entries.
     *
     * @return array{mapped: int, skipped: int, mappings: array<int, array{magento_category_id: int, taxonomy_path: string}>}
     */
    public function map(?int $rootCategoryId = null): array
    {
        $categories = $this->categoryCollectionFactory->create();
        $categories->addAttributeToSelect(['name', 'is_active']);
        $categories->addAttributeToFilter('is_active', 1);
        $categories->addAttributeToFilter('level', ['gt' => 1]);
        if ($rootCategoryId) {
            $categories->addAttributeToFilter('path', ['like' => '%/' . $rootCategoryId . '/%']);
        }

        $mapped = 0;
        $skipped = 0;
        $mappings = [];

        foreach ($categories as $category) {
            $name = trim((string)$category->getName());
            if ($name === '') {
                $skipped++;
                continue;
            }

            $match = $this->findClosestTaxonomyPath($name);
            if (!$match) {
                $skipped++;
                continue;
            }

            $payload = [
                'magento_category_id' => (int)$category->getId(),
                'taxonomy_path' => $match,
            ];

            try {
                $this->categoryMappingRepository->save($payload);
                $mapped++;
                $mappings[] = $payload;
            } catch (\Throwable $e) {
                $this->logger->warning('Taxonomy AutoMapper save failed: ' . $e->getMessage());
                $skipped++;
            }
        }

        return [
            'mapped' => $mapped,
            'skipped' => $skipped,
            'mappings' => $mappings,
        ];
    }

    private function findClosestTaxonomyPath(string $categoryName): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName('haerriz_google_shopping_feed_taxonomy');

        if (!$connection->isTableExists($table)) {
            return null;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $categoryName) . '%';
        $candidates = $connection->fetchAll(
            $connection->select()
                ->from($table, ['taxonomy_id', 'full_path'])
                ->where('full_path LIKE ?', $like)
                ->limit(25)
        );

        if (!$candidates) {
            // Fallback: match on last path segment token.
            $token = preg_replace('/[^a-z0-9]+/i', '%', $categoryName);
            $candidates = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['taxonomy_id', 'full_path'])
                    ->where('full_path LIKE ?', '%' . $token . '%')
                    ->limit(25)
            );
        }

        if (!$candidates) {
            return null;
        }

        $bestPath = null;
        $bestScore = -1.0;
        $needle = strtolower($categoryName);

        foreach ($candidates as $candidate) {
            $path = (string)$candidate['full_path'];
            $haystack = strtolower($path);
            similar_text($needle, $haystack, $percent);
            $leaf = strtolower(trim((string)substr($path, (int)strrpos($path, '>') + 1)));
            if ($leaf === $needle) {
                $percent += 25;
            } elseif (str_contains($leaf, $needle) || str_contains($needle, $leaf)) {
                $percent += 10;
            }
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $bestPath = $path;
            }
        }

        return $bestPath;
    }
}
