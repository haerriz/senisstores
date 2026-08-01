<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConfigurableExportMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'children_only', 'label' => __('Children Only')],
            ['value' => 'parent_only', 'label' => __('Parent Only')],
            ['value' => 'parent_and_children', 'label' => __('Parent and Children')],
        ];
    }
}
