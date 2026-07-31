<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Job;

use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Cancel extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::history';

    private $jobs;
    private $date;

    public function __construct(Action\Context $context, FeedJobRepositoryInterface $jobs, DateTime $date)
    {
        parent::__construct($context);
        $this->jobs = $jobs;
        $this->date = $date;
    }

    public function execute()
    {
        try {
            $job = $this->jobs->getById((int)$this->getRequest()->getParam('id'));
            if ($job->getStatus() !== 'pending') {
                throw new \InvalidArgumentException('Only queued jobs can be cancelled safely.');
            }
            $job->setStatus('cancelled');
            $job->setFinishedAt($this->date->gmtDate());
            $this->jobs->save($job);
            $this->messageManager->addSuccessMessage(__('The queued job was cancelled.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('The job cannot be cancelled safely in its current state.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
