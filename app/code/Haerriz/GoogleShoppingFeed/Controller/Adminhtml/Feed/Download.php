<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem;

class Download extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::download';

    private $repository;
    private $filesystem;
    private $fileFactory;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        Filesystem $filesystem,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->repository  = $repository;
        $this->filesystem  = $filesystem;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $id = (int)$this->getRequest()->getParam('id');
            if ($id <= 0) {
                $this->messageManager->addErrorMessage(__('Invalid feed profile ID.'));
                return $redirect->setPath('*/*/');
            }

            $profile  = $this->repository->getById($id);
            $rawName  = basename((string)$profile->getFilename());

            if (!$rawName) {
                $this->messageManager->addErrorMessage(__('Profile has an invalid filename.'));
                return $redirect->setPath('*/*/');
            }

            $directoryRead = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

            // Candidate paths in order of preference (most recent first)
            $candidates = [
                $rawName,                                          // correct: media root
                'google_feed/' . $rawName,                        // legacy subdirectory
                'google_feed/profile_' . $id . '/' . $rawName,   // legacy profile subdir
                'pub/media/' . $rawName,                          // accidental double-path (old bug)
            ];

            $resolvedPath = null;
            foreach ($candidates as $candidate) {
                if ($directoryRead->isFile($candidate)) {
                    $resolvedPath = $candidate;
                    break;
                }
            }

            if (!$resolvedPath) {
                $this->messageManager->addNoticeMessage(__(
                    'Feed file "%1" has not been generated yet. Click "Generate Now" or wait for cron.',
                    $rawName
                ));
                return $redirect->setPath('*/*/');
            }

            $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
            $contentTypeMap = [
                'xml'  => 'application/xml',
                'csv'  => 'text/csv',
                'txt'  => 'text/plain',
                'tsv'  => 'text/tab-separated-values',
                'jsonl'=> 'application/x-ndjson',
                'json' => 'application/json',
            ];
            $contentType = $contentTypeMap[$ext] ?? 'application/octet-stream';

            return $this->fileFactory->create(
                $rawName,
                ['type' => 'filename', 'value' => $resolvedPath, 'rm' => false],
                DirectoryList::MEDIA,
                $contentType
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Download failed: %1', $e->getMessage()));
            return $redirect->setPath('*/*/');
        }
    }
}
