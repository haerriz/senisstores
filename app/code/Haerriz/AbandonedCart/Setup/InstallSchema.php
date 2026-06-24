<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();
        $tableName = $setup->getTable('haerriz_abandoned_cart_email');

        if (!$connection->isTableExists($tableName)) {
            $table = $connection->newTable($tableName)
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Entity ID'
                )
                ->addColumn(
                    'quote_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'Quote ID'
                )
                ->addColumn(
                    'store_id',
                    Table::TYPE_SMALLINT,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'Store ID'
                )
                ->addColumn(
                    'recipient_email',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Recipient Email'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_TEXT,
                    16,
                    ['nullable' => false, 'default' => 'sent'],
                    'Status'
                )
                ->addColumn(
                    'sent_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Sent At'
                )
                ->addIndex(
                    $setup->getIdxName('haerriz_abandoned_cart_email', ['quote_id'], AdapterInterface::INDEX_TYPE_UNIQUE),
                    ['quote_id'],
                    ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
                )
                ->addIndex(
                    $setup->getIdxName('haerriz_abandoned_cart_email', ['sent_at']),
                    ['sent_at']
                )
                ->setComment('Haerriz Abandoned Cart Email Log');

            $connection->createTable($table);
        }

        $setup->endSetup();
    }
}
