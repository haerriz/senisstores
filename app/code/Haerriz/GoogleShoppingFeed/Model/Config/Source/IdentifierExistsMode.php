<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IdentifierExistsMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'auto', 'label' => __('Auto')],
            ['value' => 'always_yes', 'label' => __('Always Yes')],
            ['value' => 'always_no', 'label' => __('Always No')],
        ];
    }
}
