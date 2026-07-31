<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_management';

    private $pageFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Haerriz_GoogleShoppingFeed::dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('Google Shopping Feed - Dashboard'));
        return $resultPage;
    }
}
