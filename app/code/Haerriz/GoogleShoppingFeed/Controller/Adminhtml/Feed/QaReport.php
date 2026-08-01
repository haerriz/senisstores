<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\Quality\CompletenessScorer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;

class QaReport extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private FeedProfileRepositoryInterface $profileRepository;
    private CompletenessScorer $completenessScorer;
    private FileFactory $fileFactory;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $profileRepository,
        CompletenessScorer $completenessScorer,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->profileRepository = $profileRepository;
        $this->completenessScorer = $completenessScorer;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $profileId = (int)$this->getRequest()->getParam('id');
        if ($profileId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid profile id.'));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
        }

        try {
            $profile = $this->profileRepository->getById($profileId);
            $rows = $this->completenessScorer->toReportRows($profile, 500);

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['sku', 'missing_field', 'severity', 'guidance']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['sku'] ?? '',
                    $row['field'] ?? '',
                    $row['severity'] ?? 'warning',
                    $row['guidance'] ?? '',
                ]);
            }
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            $filename = sprintf('qa_report_profile_%d_%s.csv', $profileId, date('Ymd_His'));
            return $this->fileFactory->create(
                $filename,
                $content,
                \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('QA report failed: %1', $e->getMessage()));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/edit', ['id' => $profileId]);
        }
    }
}
