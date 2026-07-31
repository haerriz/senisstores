<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class ValidateButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Validate Profile'),
            'class' => 'action-secondary',
            'sort_order' => 30,
        ];
    }
}
