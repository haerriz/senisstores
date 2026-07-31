<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;

class Console extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $pageFactory;
    private $repository;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        FeedProfileRepositoryInterface $repository
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->repository  = $repository;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid profile identifier.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        try {
            $profile = $this->repository->getById($id);
            $resultPage = $this->pageFactory->create();
            $resultPage->setActiveMenu('Haerriz_GoogleShoppingFeed::feed_profiles');
            $resultPage->getConfig()->getTitle()->prepend(__('Generating Feed: %1', $profile->getName()));
            return $resultPage;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
    }
}
