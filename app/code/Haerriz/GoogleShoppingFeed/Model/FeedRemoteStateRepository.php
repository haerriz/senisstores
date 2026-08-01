<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedRemoteStateInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedRemoteStateRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedRemoteState as FeedRemoteStateResource;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedRemoteState\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;

class FeedRemoteStateRepository implements FeedRemoteStateRepositoryInterface
{
    private FeedRemoteStateResource $resource;
    private FeedRemoteStateFactory $stateFactory;
    private CollectionFactory $collectionFactory;
    private ResourceConnection $resourceConnection;

    public function __construct(
        FeedRemoteStateResource $resource,
        FeedRemoteStateFactory $stateFactory,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->resource = $resource;
        $this->stateFactory = $stateFactory;
        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function save(FeedRemoteStateInterface $state)
    {
        try {
            $this->resource->save($state);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save remote state: %1', $e->getMessage()), $e);
        }

        return $state;
    }

    public function getByOfferIdAndProfile(string $offerId, $profileId = null): FeedRemoteStateInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('offer_id', $offerId);
        if ($profileId === null || $profileId === '') {
            $collection->addFieldToFilter('profile_id', ['null' => true]);
        } else {
            $collection->addFieldToFilter('profile_id', (int)$profileId);
        }
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();
        if ($item && $item->getId()) {
            return $item;
        }

        /** @var FeedRemoteStateInterface $state */
        $state = $this->stateFactory->create();
        $state->setOfferId($offerId);
        if ($profileId !== null && $profileId !== '') {
            $state->setProfileId((int)$profileId);
        } else {
            $state->setProfileId(null);
        }
        $state->setProductId(0);
        $state->setSyncStatus('unknown');

        return $state;
    }

    public function getStatusCounts(?int $profileId = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName('haerriz_google_shopping_feed_remote_state');

        $select = $connection->select()
            ->from($table, ['sync_status', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->group('sync_status');

        if ($profileId !== null && $profileId > 0) {
            $select->where('profile_id = ?', $profileId);
        }

        $rows = $connection->fetchPairs($select);
        $counts = [
            'approved' => 0,
            'disapproved' => 0,
            'pending' => 0,
            'synced' => 0,
            'unknown' => 0,
        ];

        foreach ($rows as $status => $count) {
            $key = strtolower((string)$status);
            if (str_contains($key, 'approv') && !str_contains($key, 'dis')) {
                $counts['approved'] += (int)$count;
            } elseif (str_contains($key, 'disapprov') || str_contains($key, 'reject')) {
                $counts['disapproved'] += (int)$count;
            } elseif (str_contains($key, 'pend') || str_contains($key, 'review')) {
                $counts['pending'] += (int)$count;
            } elseif (isset($counts[$key])) {
                $counts[$key] += (int)$count;
            } else {
                $counts['unknown'] += (int)$count;
            }
        }

        return $counts;
    }

    /**
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRecentDisapproved(int $limit = 10): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName('haerriz_google_shopping_feed_remote_state');

        $select = $connection->select()
            ->from($table, ['offer_id', 'profile_id', 'sync_status', 'issues', 'synced_at', 'updated_at'])
            ->where('sync_status LIKE ?', '%disapprov%')
            ->orWhere('sync_status LIKE ?', '%reject%')
            ->order('updated_at DESC')
            ->limit(max(1, $limit));

        return $connection->fetchAll($select) ?: [];
    }
}
