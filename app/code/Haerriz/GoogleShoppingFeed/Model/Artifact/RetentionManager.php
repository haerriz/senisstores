<?php
namespace Haerriz\GoogleShoppingFeed\Model\Artifact;

use Haerriz\GoogleShoppingFeed\Api\FeedArtifactRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class RetentionManager
{
    private const DEFAULT_KEEP = 10; // keep last N artifacts per profile

    private $repository;
    private $searchCriteriaBuilder;
    private $logger;

    public function __construct(
        FeedArtifactRepositoryInterface $repository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->repository            = $repository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger                = $logger;
    }

    /**
     * Delete physical files and DB records for expired artifacts.
     * Keeps the most recent $keepCount per profile.
     */
    public function cleanup(int $keepCount = self::DEFAULT_KEEP): int
    {
        $deleted = 0;
        try {
            $criteria = $this->searchCriteriaBuilder->create();
            $artifacts = $this->repository->getList($criteria)->getItems();

            // Group by profile
            $byProfile = [];
            foreach ($artifacts as $artifact) {
                $byProfile[$artifact->getProfileId()][] = $artifact;
            }

            foreach ($byProfile as $profileId => $items) {
                // Sort newest first (by created_at desc)
                usort($items, fn($a, $b) => strcmp($b->getCreatedAt(), $a->getCreatedAt()));
                $toDelete = array_slice($items, $keepCount);

                foreach ($toDelete as $artifact) {
                    $path = $artifact->getFilePath();
                    if ($path && file_exists($path)) {
                        unlink($path);
                    }
                    $this->repository->delete($artifact);
                    $deleted++;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("RetentionManager::cleanup failed: " . $e->getMessage());
        }

        return $deleted;
    }
}
