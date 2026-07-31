<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Job;

use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Retry extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::history';

    private $jobs;
    private $profiles;
    private $generator;

    public function __construct(
        Action\Context $context,
        FeedJobRepositoryInterface $jobs,
        FeedProfileRepositoryInterface $profiles,
        FeedGenerator $generator
    ) {
        parent::__construct($context);
        $this->jobs = $jobs;
        $this->profiles = $profiles;
        $this->generator = $generator;
    }

    public function execute()
    {
        try {
            $job = $this->jobs->getById((int)$this->getRequest()->getParam('id'));
            if (!in_array($job->getStatus(), ['failed', 'partial', 'cancelled'], true) || !$job->getProfileId()) {
                throw new \InvalidArgumentException('Only failed, partial, or cancelled jobs with a retained profile can be retried.');
            }
            $profile = $this->profiles->getById($job->getProfileId());
            $this->generator->generate($profile, 'retry');
            $this->messageManager->addSuccessMessage(__('The retry completed. Review the new job entry.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('The job could not be retried. Verify its status and retained profile.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
