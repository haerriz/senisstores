<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeedType implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'xml', 'label' => __('XML')],
            ['value' => 'csv', 'label' => __('CSV')]
        ];
    }
}
