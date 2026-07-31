<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class PreviewButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Preview Feed'),
            'class' => 'action-secondary',
            'sort_order' => 20,
        ];
    }
}
