<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Setup;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use WeSupply\Toolbox\Api\Data\OrderInterface;
use WeSupply\Toolbox\Api\OrderRepositoryInterface;
use Zend_Db_Exception;

/**
 * Class UpgradeSchema
 * @package WeSupply\Toolbox\Setup
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    private $wsOrderRepository;

    /**
     * @param SortOrderBuilder         $sortOrderBuilder
     * @param SearchCriteriaBuilder    $searchCriteriaBuilder
     * @param OrderRepositoryInterface $wsOrderRepository
     */
    public function __construct(
        SortOrderBuilder $sortOrderBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $wsOrderRepository
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->wsOrderRepository = $wsOrderRepository;
    }

    /**
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     *
     * @throws Zend_Db_Exception
     * @throws LocalizedException
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1') < 0) {

            $setup->getConnection()->addColumn(
                $setup->getTable('wesupply_orders'),
                'store_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'nullable' => true,
                    'unsigned' => true,
                    'comment' => 'Store Id'
                ]
            );

            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('wesupply_orders'),
                    $setup->getIdxName('wesupply_orders', ['store_id']),
                    ['store_id']
                );
        }

        if (version_compare($context->getVersion(), '1.0.7') < 0) {
            $setup->getConnection()->addColumn(
                $setup->getTable('wesupply_orders'),
                'is_excluded',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'nullable' => true,
                    'unsigned' => true,
                    'default' => '0',
                    'comment' => 'Order was excluded from export'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.9') < 0) {

            /** sales_quote */
            $setup->getConnection()->addColumn(
                $setup->getTable('quote'),
                'delivery_timestamp',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' =>'Delivery Timestamp'
                ]
            );

            /** sales order */
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'delivery_timestamp',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' =>'Delivery Timestamp'
                ]
            );

            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'delivery_utc_offset',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' =>'Delivery UTC Offset'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.10') < 0) {
            $setup->getConnection()->addColumn(
                $setup->getTable('wesupply_orders'),
                'order_number',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => false,
                    'after'    => 'order_id',
                    'comment'  => 'Order Increment ID'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.11') < 0) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'exclude_import_pending',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'nullable' => true,
                    'unsigned' => true,
                    'default' => '0',
                    'after'    => 'delivery_utc_offset',
                    'comment' => 'Exclude order while is pending from export'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.12') < 0) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'exclude_import_complete',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'nullable' => true,
                    'unsigned' => true,
                    'default' => '0',
                    'after'    => 'exclude_import_pending',
                    'comment' => 'Exclude complete order from export'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.13') < 0) {
            $setup->getConnection()->addColumn(
                $setup->getTable('wesupply_orders'),
                'awaiting_update',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                    'nullable' => false,
                    'unsigned' => true,
                    'default' => false,
                    'after'    => 'store_id',
                    'comment' => 'Order was updated by ERP or other'
                ]
            );

            $setup->getConnection()->changeColumn(
                $setup->getTable('wesupply_orders'),
                'updated_at',
                'updated_at',
                [
                    'type'=> \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                    'nullable' => false,
                    'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE,
                    'comment' => 'Updated At'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.15') < 0) {

            $setup->getConnection()->dropTable(
                $setup->getTable('wesupply_returns_list')
            );

            $table = $setup->getConnection()
                ->newTable($setup->getTable('wesupply_returns_list'))
                ->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'primary' => true,
                        'nullable' => false,
                        'unsigned' => true,
                        'comment' => 'Id'
                    ]
                )->addColumn(
                    'return_reference',
                    \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                        'comment' => 'Return Reference ID'
                    ]
                )->addColumn(
                    'status',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    [
                        'nullable' => true,
                        'default' => '',
                        'comment' => 'Return Status'
                    ]
                )->addColumn(
                    'refunded',
                    \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                    null,
                    [
                        'nullable' => false,
                        'unsigned' => true,
                        'default' => false,
                        'comment' => 'Refund Status'
                    ]
                )->addColumn(
                    'creditmemo_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    [
                        'nullable' => true,
                        'default' => '',
                        'comment' => 'CreditMemo Increment ID'
                    ]
                )->addColumn(
                    'request_log_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    [
                        'nullable' => true,
                        'default' => '',
                        'comment' => 'Request Log ID'
                    ]
                );

            $setup->getConnection()->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.0.16') < 0) {

            $this->deleteHistoricalOrders($setup);

            $tableDescribe = $setup->getConnection()->describeTable($setup->getTable('wesupply_orders'));
            if ($tableDescribe['order_id']['DATA_TYPE'] != 'int') {
                $setup->getConnection()->changeColumn(
                    $setup->getTable('wesupply_orders'),
                    'order_id',
                    'order_id',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'nullable' => false,
                        'comment' => 'Order Id'
                    ]
                );
            }
            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('wesupply_orders'),
                    $setup->getIdxName('wesupply_orders', ['order_id']),
                    ['order_id']
                );

            $setup->getConnection()->changeColumn(
                $setup->getTable('wesupply_orders'),
                'order_number',
                'order_number',
                [
                    'type'=> \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => false,
                    'length' => 32,
                    'default' => '',
                    'comment' => 'Order Number'
                ]
            );

            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('wesupply_orders'),
                    $setup->getIdxName('wesupply_orders', ['order_number']),
                    ['order_number']
                );
        }

        if (version_compare($context->getVersion(), '1.0.17') < 0 ) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_shipment_track'),
                'wesupply_order_update',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                    'nullable' => true,
                    'unsigned' => true,
                    'default' => 0,
                    'after'    => 'updated_at',
                    'comment' => 'Wesupply Order Update Flag'
                ]
            );

            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('sales_shipment_track'),
                    $setup->getIdxName('sales_shipment_track', ['wesupply_order_update']),
                    ['wesupply_order_update']
                );

            $endDate = date('Y-m-d H:i:s', strtotime('-1 day', time()));
            $shipmentTrackTableName = $setup->getTable('sales_shipment_track');
            $setup->getConnection()->query( "UPDATE " . $shipmentTrackTableName . " SET `wesupply_order_update` = 1 WHERE (created_at <= '" . $endDate . "')");
        }

        if (version_compare($context->getVersion(), '1.0.18') < 0 ) {
            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('wesupply_orders'),
                    $setup->getIdxName('wesupply_orders', ['awaiting_update']),
                    ['awaiting_update']
                );
        }

        if (version_compare($context->getVersion(), '1.0.19') < 0 ) {
            $setup->getConnection()
                ->dropIndex(
                    $setup->getTable('wesupply_orders'),
                    $setup->getIdxName('wesupply_orders', ['store_id'])
                );
            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('wesupply_orders'),
                    $setup->getIdxName('wesupply_orders', ['store_id', 'updated_at', 'is_excluded']),
                    ['store_id', 'updated_at', 'is_excluded']
                );
        }

        if (version_compare($context->getVersion(), '1.0.20') < 0) {
            $setup->getConnection()->addColumn(
                $setup->getTable('wesupply_returns_list'),
                'return_split_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'unsigned' => true,
                    'default' => null,
                    'after'    => 'request_log_id',
                    'comment' => 'Return Split ID'
                ]
            );
        }

        $setup->endSetup();
    }

    /**
     * @param $setup
     */
    private function deleteHistoricalOrders($setup)
    {
        $endDate = date('Y-m-d H:i:s', strtotime('-1 day', time()));

        $conn = $setup->getConnection();
        $tableName = $conn->getTableName($setup->getTable('wesupply_orders'));

        $whereConditions = [
            $conn->quoteInto('updated_at < ?', $endDate)
        ];

        $conn->delete($tableName, $whereConditions);
    }
}
