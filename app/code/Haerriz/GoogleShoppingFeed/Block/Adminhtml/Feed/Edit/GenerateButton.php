<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class GenerateButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Generate Feed Now'),
            'class' => 'action-primary',
            'sort_order' => 60,
        ];
    }
}
