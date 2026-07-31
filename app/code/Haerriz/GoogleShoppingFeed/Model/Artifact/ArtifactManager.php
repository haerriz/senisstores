<?php
namespace Haerriz\GoogleShoppingFeed\Model\Artifact;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedArtifactRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedArtifactFactory;
use Psr\Log\LoggerInterface;

class ArtifactManager
{
    private $repository;
    private $factory;
    private $logger;

    public function __construct(
        FeedArtifactRepositoryInterface $repository,
        FeedArtifactFactory $factory,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->factory    = $factory;
        $this->logger     = $logger;
    }

    /**
     * Record an immutable artifact entry after a successful export.
     */
    public function record(
        FeedProfileInterface $profile,
        string $absolutePath,
        int $fileSize,
        string $checksum,
        int $exportedCount
    ): void {
        try {
            $artifact = $this->factory->create();
            $artifact->setProfileId((int)$profile->getId());
            $artifact->setFilePath($absolutePath);
            $artifact->setFileSize($fileSize);
            $artifact->setChecksum($checksum);
            $artifact->setExportedCount($exportedCount);
            $artifact->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->save($artifact);
        } catch (\Exception $e) {
            $this->logger->warning("ArtifactManager: Could not record artifact: " . $e->getMessage());
        }
    }

    /**
     * Get history for a profile (last N artifacts).
     */
    public function getHistory(int $profileId, int $limit = 10): array
    {
        try {
            $criteria = $this->repository->getList(
                (new \Magento\Framework\Api\SearchCriteriaBuilder())
                    ->addFilter('profile_id', $profileId)
                    ->setCurrentPage(1)
                    ->setPageSize($limit)
                    ->create()
            );
            return $criteria->getItems();
        } catch (\Exception $e) {
            $this->logger->warning("ArtifactManager: Could not fetch history: " . $e->getMessage());
            return [];
        }
    }
}
