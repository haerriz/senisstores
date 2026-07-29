<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;

class NewAction extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feeds';

    public function execute()
    {
        $this->_forward('edit');
    }
}
