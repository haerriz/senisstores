<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class TestConnectionButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Test Delivery Connection'),
            'class' => 'action-secondary',
            'sort_order' => 40,
        ];
    }
}
