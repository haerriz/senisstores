<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;

class Trigger extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    protected $repository;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository
    ) {
        $this->repository = $repository;
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $action = $this->getRequest()->getParam('action');
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid profile identifier.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $profile = $this->repository->getById($id);
            switch ($action) {
                case 'run':
                    // Phase 1 UI: Redirect to the live console page instead of running synchronously
                    return $resultRedirect->setPath('*/*/console', ['id' => $profile->getId()]);

                case 'enable':
                    $profile->setStatus(1);
                    $this->repository->save($profile);
                    $this->messageManager->addSuccessMessage(__('Feed profile schedule enabled.'));
                    break;

                case 'disable':
                    $profile->setStatus(0);
                    $this->repository->save($profile);
                    $this->messageManager->addSuccessMessage(__('Feed profile schedule disabled.'));
                    break;
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
