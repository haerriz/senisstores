<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Job;

use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Exception\LocalizedException;

class Download extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::download';

    private $jobs;
    private $filesystem;
    private $fileFactory;

    public function __construct(
        Action\Context $context,
        FeedJobRepositoryInterface $jobs,
        Filesystem $filesystem,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->jobs = $jobs;
        $this->filesystem = $filesystem;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $job = $this->jobs->getById((int)$this->getRequest()->getParam('id'));
        $path = ltrim((string)$job->getData('artifact_path'), '/');
        if (!preg_match('#^google_feed/profile_[0-9]+/[^/]+$#', $path)
            || !$this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile($path)
        ) {
            throw new LocalizedException(__('This job has no retained downloadable artifact.'));
        }
        return $this->fileFactory->create(
            basename($path),
            ['type' => 'filename', 'value' => $path, 'rm' => false],
            DirectoryList::MEDIA,
            'application/octet-stream'
        );
    }
}
