<?php
namespace WeltPixel\OwlCarouselSlider\Setup\Patch\Schema;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class ModifySliderTableColumn implements SchemaPatchInterface, PatchVersionInterface
{
    /**
     * @var SchemaSetupInterface $schemaSetup
     */
    private $schemaSetup;

    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $setup = $this->schemaSetup;
        $setup->startSetup();
        $connection = $setup->getConnection();
        $bannersTableName = $setup->getTable('weltpixel_owlcarouselslider_banners');
        $slidersTableName = $setup->getTable('weltpixel_owlcarouselslider_sliders');
        if (!$connection->tableColumnExists($bannersTableName, 'custom_css')) {
            $connection->addColumn(
                $bannersTableName,
                'custom_css',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => '64k',
                    'nullable' => true,
                    'after' => 'custom_content',
                    'comment' => 'Custom CSS'
                ]
            );
        }

        if (!$connection->tableColumnExists($bannersTableName, 'ga_promo_id')) {
            $connection->addColumn(
                $bannersTableName,
                'ga_promo_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => '256',
                    'nullable' => false,
                    'default' => '',
                    'comment' => 'GA Promo ID'
                ]
            );
        }

        if (!$connection->tableColumnExists($bannersTableName, 'ga_promo_name')) {
            $connection->addColumn(
                $bannersTableName,
                'ga_promo_name',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => '256',
                    'nullable' => false,
                    'default' => '',
                    'comment' => 'GA Promo Name'
                ]
            );
        }

        if (!$connection->tableColumnExists($bannersTableName, 'ga_promo_creative')) {
            $connection->addColumn(
                $bannersTableName,
                'ga_promo_creative',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => '256',
                    'nullable' => false,
                    'default' => '',
                    'comment' => 'GA Promo Creative'
                ]
            );
        }

        if (!$connection->tableColumnExists($bannersTableName, 'ga_promo_position')) {
            $connection->addColumn(
                $bannersTableName,
                'ga_promo_position',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => '256',
                    'nullable' => false,
                    'default' => '',
                    'comment' => 'GA Promo Position'
                ]
            );
        }


        if (!$connection->tableColumnExists($slidersTableName, 'scheduled_ajax')) {
            $connection->addColumn(
                $slidersTableName,
                'scheduled_ajax',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'nullable' => true,
                    'unsigned' => true,
                    'default' => 0,
                    'comment' => 'Ajax Calls required for Scheduled Banners',
                    'after' => 'slider_content'
                ]
            );
        }

        $setup->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion()
    {
        return '1.0.6';
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [
            AddSliderTables::class
        ];
    }
}
