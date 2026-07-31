<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;

class TriggerAjax extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $jsonFactory;
    private $repository;
    private $generator;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        FeedProfileRepositoryInterface $repository,
        FeedGenerator $generator
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->repository  = $repository;
        $this->generator   = $generator;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $id = (int)$this->getRequest()->getParam('id');

        // We run this in the background by returning early to the browser.
        // FastCGI/FPM makes this slightly tricky, but we can just run it synchronously 
        // since the browser is polling via a different connection anyway.
        // However, a long-running sync request might block the session.
        // We MUST write-close the session before generating!
        session_write_close();

        try {
            $profile = $this->repository->getById($id);
            $this->generator->generate($profile, 'manual');
            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            return $result->setHttpResponseCode(500)->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
