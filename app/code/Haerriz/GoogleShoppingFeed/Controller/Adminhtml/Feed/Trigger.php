<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;

class Trigger extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_profiles';

    /**
     * @var FeedProfileRepositoryInterface
     */
    protected $repository;

    /**
     * @var FeedGenerator
     */
    protected $generator;

    /**
     * @param Context $context
     * @param FeedProfileRepositoryInterface $repository
     * @param FeedGenerator $generator
     */
    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        FeedGenerator $generator
    ) {
        $this->repository = $repository;
        $this->generator = $generator;
        parent::__construct($context);
    }

    /**
     * Handle manual actions (run, enable, disable)
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
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
                    $success = $this->generator->generate($profile);
                    if ($success) {
                        $this->messageManager->addSuccessMessage(__('Feed profile execution completed successfully.'));
                    } else {
                        $this->messageManager->addErrorMessage(__('Feed profile execution failed.'));
                    }
                    break;

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

                default:
                    $this->messageManager->addErrorMessage(__('Unsupported action.'));
                    break;
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
