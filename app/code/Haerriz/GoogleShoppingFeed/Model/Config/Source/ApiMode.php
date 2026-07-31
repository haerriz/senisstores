<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApiMode implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'production', 'label' => __('Production (Live Google Merchant Center API)')],
            ['value' => 'sandbox', 'label' => __('Sandbox / Dry Run Mode')]
        ];
    }
}
