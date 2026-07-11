<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Model;

use Magento\Framework\App\ResourceConnection;

class QuoteFinder
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $config
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
    }

    /**
     * @param int|null $storeId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function findAbandoned($storeId, $limit)
    {
        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $logTable = $this->resourceConnection->getTableName('haerriz_abandoned_cart_email');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        $hours = $this->config->getAbandonAfterHours($storeId);
        $maxDays = $this->config->getMaxCartAgeDays($storeId);

        $select = $connection->select()
            ->from(['q' => $quoteTable], [
                'entity_id', 'store_id', 'customer_id', 'customer_email',
                'customer_firstname', 'customer_lastname', 'grand_total', 'items_count', 'updated_at',
            ])
            ->joinLeft(['log' => $logTable], 'log.quote_id = q.entity_id', [])
            ->joinLeft(['ce' => $customerTable], 'ce.entity_id = q.customer_id', ['account_email' => 'email'])
            ->where('q.is_active = ?', 1)
            ->where('q.items_count > ?', 0)
            ->where('log.entity_id IS NULL')
            ->where('q.updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? HOUR)', $hours)
            ->where('q.updated_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', $maxDays)
            ->where('NOT EXISTS (SELECT 1 FROM ' . $orderTable . ' so WHERE so.quote_id = q.entity_id)')
            ->where('(q.customer_email IS NOT NULL AND q.customer_email != "") OR q.customer_id > 0')
            ->order('q.updated_at ASC')
            ->limit($limit);

        if ($storeId !== null) {
            $select->where('q.store_id = ?', $storeId);
        }

        $rows = $connection->fetchAll($select);
        $result = [];

        foreach ($rows as $row) {
            $email = trim((string) ($row['customer_email'] ?: (isset($row['account_email']) ? $row['account_email'] : '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $result[] = [
                'quote_id' => (int) $row['entity_id'],
                'store_id' => (int) $row['store_id'],
                'customer_id' => (int) $row['customer_id'],
                'email' => $email,
                'firstname' => (string) ($row['customer_firstname'] ?: 'Customer'),
                'lastname' => (string) ($row['customer_lastname'] ?: ''),
                'grand_total' => (float) $row['grand_total'],
                'items_count' => (int) $row['items_count'],
            ];
        }

        return $result;
    }
}
