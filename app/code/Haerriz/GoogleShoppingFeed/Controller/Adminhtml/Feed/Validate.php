<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\ProfileValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Validate extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $repository;
    private $validator;
    private $jsonFactory;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        ProfileValidator $validator,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->validator = $validator;
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $profile = $this->repository->getById((int)$this->getRequest()->getParam('id'));
            return $result->setData($this->validator->validate($profile));
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'valid' => false,
                'errors' => ['profile' => [__('Unable to validate this profile.')]],
                'warnings' => [],
            ]);
        }
    }
}
