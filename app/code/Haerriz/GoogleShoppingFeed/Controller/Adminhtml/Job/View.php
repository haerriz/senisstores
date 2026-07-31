<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Job;

use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::history';

    private $jobRepository;
    private $pageFactory;
    private $redirectFactory;

    public function __construct(
        Context $context,
        FeedJobRepositoryInterface $jobRepository,
        PageFactory $pageFactory,
        RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);
        $this->jobRepository = $jobRepository;
        $this->pageFactory = $pageFactory;
        $this->redirectFactory = $redirectFactory;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        try {
            $this->jobRepository->getById($id);
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(__('The requested feed job no longer exists.'));
            return $this->redirectFactory->create()->setPath('*/*/index');
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Haerriz_GoogleShoppingFeed::jobs');
        $page->getConfig()->getTitle()->prepend(__('Feed Job #%1', $id));
        return $page;
    }
}
