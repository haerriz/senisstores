<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\Storage\ConnectionTester;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class TestConnection extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $repository;
    private $tester;
    private $jsonFactory;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        ConnectionTester $tester,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->tester = $tester;
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $profile = $this->repository->getById((int)$this->getRequest()->getParam('id'));
            return $result->setData(['success' => (bool)$this->tester->test($profile)]);
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('Connection failed. Verify the host, fingerprint, credentials, and path.'),
            ]);
        }
    }
}
