<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterfaceFactory;

class SetupGoogle extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::feed_save';

    private $profileRepository;
    private $profileFactory;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $profileRepository,
        FeedProfileInterfaceFactory $profileFactory
    ) {
        parent::__construct($context);
        $this->profileRepository = $profileRepository;
        $this->profileFactory = $profileFactory;
    }

    public function execute()
    {
        try {
            $profile = $this->profileFactory->create();
            $profile->setName('Google Shopping Feed');
            $profile->setFeedType('google_shopping_v1');
            $profile->setFilename('google_shopping.xml');
            $profile->setStatus(1);
            $profile->setStoreId(1);
            $profile->setCronExpr('0 3 * * *');
            
            $saved = $this->profileRepository->save($profile);
            $this->messageManager->addSuccessMessage(__('Default Google Shopping Feed profile created successfully!'));
            return $this->_redirect('*/*/edit', ['id' => $saved->getEntityId() ?? $saved->getProfileId()]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error setting up Google Feed: %1', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }
    }
}
