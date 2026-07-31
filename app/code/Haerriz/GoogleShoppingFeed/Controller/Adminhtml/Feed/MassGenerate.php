<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassGenerate extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $filter;
    private $collectionFactory;
    private $generator;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        FeedGenerator $generator
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->generator = $generator;
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $success = 0;
        $failed = 0;
        foreach ($collection as $profile) {
            $this->generator->generate($profile, 'manual') ? $success++ : $failed++;
        }
        $this->messageManager->addSuccessMessage(__('%1 feed(s) generated.', $success));
        if ($failed) {
            $this->messageManager->addErrorMessage(__('%1 feed(s) failed. Review job history.', $failed));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
