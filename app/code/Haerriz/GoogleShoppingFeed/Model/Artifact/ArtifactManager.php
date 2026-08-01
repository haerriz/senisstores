<?php
namespace Haerriz\GoogleShoppingFeed\Model\Artifact;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedArtifactRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedArtifactFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class ArtifactManager
{
    private FeedArtifactRepositoryInterface $repository;
    private FeedArtifactFactory $factory;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private LoggerInterface $logger;

    public function __construct(
        FeedArtifactRepositoryInterface $repository,
        FeedArtifactFactory $factory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->factory = $factory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

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
            $this->logger->warning('ArtifactManager: Could not record artifact: ' . $e->getMessage());
        }
    }

    public function getHistory(int $profileId, int $limit = 10): array
    {
        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('profile_id', $profileId)
                ->setCurrentPage(1)
                ->setPageSize($limit)
                ->create();
            return array_values($this->repository->getList($criteria)->getItems());
        } catch (\Exception $e) {
            $this->logger->warning('ArtifactManager: Could not fetch history: ' . $e->getMessage());
            return [];
        }
    }
}
