<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedProfileCloner;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Duplicate extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::manage';

    private $repository;
    private $cloner;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        FeedProfileCloner $cloner
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->cloner     = $cloner;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('id');

        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid profile ID.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $original = $this->repository->getById($id);

            // FIX 5: Use FeedProfileCloner::clone() instead of inline duplication
            $clone = $this->cloner->clone($original);
            $saved = $this->repository->save($clone);

            $this->messageManager->addSuccessMessage(
                __('Profile "%1" has been duplicated as "%2".', $original->getName(), $clone->getName())
            );
            return $redirect->setPath('*/*/edit', ['id' => $saved->getId()]);

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Duplication failed: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/');
    }
}
