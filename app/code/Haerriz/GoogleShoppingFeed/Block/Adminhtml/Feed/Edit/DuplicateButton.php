<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DuplicateButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Duplicate Profile'),
            'class' => 'action-secondary',
            'sort_order' => 50,
        ];
    }
}
