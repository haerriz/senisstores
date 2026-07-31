<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Magento\Backend\App\Action;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_profiles';

    private $repository;

    public function __construct(
        Action\Context $context,
        FeedProfileRepositoryInterface $repository
    ) {
        parent::__construct($context);
        $this->repository = $repository;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $id = (int)$this->getRequest()->getParam('id');
            $this->repository->deleteById($id);
            $this->messageManager->addSuccessMessage(
                __('The profile was deleted successfully.')
            );
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('The profile could not be deleted: %1', $exception->getMessage()));
        }

        return $redirect->setPath('*/*/');
    }
}
