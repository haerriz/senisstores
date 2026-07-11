<?php
namespace Haerriz\Shiprocket\Setup;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $attributes = [
            'shipping_length' => 'Shipping Length (cm)',
            'shipping_width' => 'Shipping Width (cm)',
            'shipping_height' => 'Shipping Height (cm)'
        ];

        foreach ($attributes as $code => $label) {
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                $code,
                [
                    'type' => 'decimal',
                    'backend' => '',
                    'frontend' => '',
                    'label' => $label,
                    'input' => 'text',
                    'class' => '',
                    'source' => '',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '',
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'unique' => false,
                    'apply_to' => '',
                    'group' => 'General'
                ]
            );
        }
    }
}
