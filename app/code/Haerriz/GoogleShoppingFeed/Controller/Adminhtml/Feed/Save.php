<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedProfileFactory;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feeds';

    protected $repository;
    protected $factory;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        FeedProfileFactory $factory
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->factory = $factory;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data) {
            try {
                $id = $data['profile_id'] ?? null;
                $model = $id ? $this->repository->getById($id) : $this->factory->create();
                
                // Serialize Dynamic Rows for attribute mapping
                if (isset($data['attributes_mapping'])) {
                    $data['attributes_mapping_serialized'] = json_encode($data['attributes_mapping']);
                } else {
                    $data['attributes_mapping_serialized'] = null;
                }

                $model->setData($data);
                $this->repository->save($model);
                
                $this->messageManager->addSuccessMessage(__('You saved the feed profile.'));
                
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        
        return $resultRedirect->setPath('*/*/');
    }
}
