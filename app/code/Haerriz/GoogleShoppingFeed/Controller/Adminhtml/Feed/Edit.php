<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_profiles';

    protected $resultPageFactory;
    protected $repository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        FeedProfileRepositoryInterface $repository
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->repository = $repository;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Haerriz_GoogleShoppingFeed::feeds');
        
        if ($id) {
            try {
                $model = $this->repository->getById($id);
                $resultPage->getConfig()->getTitle()->prepend(__('Edit Feed Profile: %1', $model->getName()));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('This feed profile no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('New Feed Profile'));
        }
        
        return $resultPage;
    }
}
