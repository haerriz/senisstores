<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassStatus extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_profiles';

    private $filter;
    private $collectionFactory;
    private $repository;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        FeedProfileRepositoryInterface $repository
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->repository = $repository;
    }

    public function execute()
    {
        $action = (string)$this->getRequest()->getParam('action');
        if (!in_array($action, ['enable', 'disable', 'delete'], true)) {
            $this->messageManager->addErrorMessage(__('Unsupported mass action.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection as $profile) {
            if ($action === 'delete') {
                $this->repository->delete($profile);
            } else {
                $profile->setStatus($action === 'enable' ? 1 : 0);
                $this->repository->save($profile);
            }
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('%1 profile(s) updated.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
