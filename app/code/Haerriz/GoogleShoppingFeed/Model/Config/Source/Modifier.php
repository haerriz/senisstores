<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Modifier implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('None')],
            ['value' => 'strip_tags', 'label' => __('Strip HTML')],
            ['value' => 'capitalize', 'label' => __('Title Case')],
            ['value' => 'round_price', 'label' => __('Round Price')],
            ['value' => 'prepend_text', 'label' => __('Prepend Text')],
            ['value' => 'append_text', 'label' => __('Append Text')],
            ['value' => 'google_taxonomy', 'label' => __('Google Taxonomy')],
        ];
    }
}
