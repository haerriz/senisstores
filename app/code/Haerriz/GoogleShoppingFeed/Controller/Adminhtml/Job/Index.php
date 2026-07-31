<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::history';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Haerriz_GoogleShoppingFeed::jobs');
        $resultPage->getConfig()->getTitle()->prepend(__('Feed Job History'));
        return $resultPage;
    }
}
