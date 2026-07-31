<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedExporter;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterfaceFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class PreviewAjax extends Action
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private $jsonFactory;
    private $exporter;
    private $profileFactory;
    private $filesystem;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        FeedExporter $exporter,
        FeedProfileInterfaceFactory $profileFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->jsonFactory    = $jsonFactory;
        $this->exporter       = $exporter;
        $this->profileFactory = $profileFactory;
        $this->filesystem     = $filesystem;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $data = $this->getRequest()->getPostValue();
        
        if (empty($data)) {
            return $result->setData(['success' => false, 'message' => 'No form data received.']);
        }

        try {
            // Create a mock profile in memory using the unsaved form data
            $profile = $this->profileFactory->create();
            foreach (['name','feed_type','store_id','filename','attributes_mapping_serialized','conditions_serialized'] as $field) {
                if (isset($data[$field])) {
                    $profile->setData($field, $data[$field]);
                }
            }
            // Ensure basic defaults if empty
            if (!$profile->getFeedType()) $profile->setFeedType('google_shopping_v1');
            if (!$profile->getFilename()) $profile->setFilename('preview_' . time() . '.tmp');

            // Generate a random temp filename in pub/media
            $tempFile = 'haerriz_feed_preview_' . uniqid() . '.tmp';
            
            // Limit to 5 products for speed
            $this->exporter->export($profile, $tempFile, null, 5);

            // Read the generated file contents
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $content = '';
            if ($directory->isFile($tempFile)) {
                $content = $directory->readFile($tempFile);
                // Clean up the temp file
                $directoryWrite = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
                $directoryWrite->delete($tempFile);
            }

            return $result->setData([
                'success' => true,
                'content' => $content
            ]);

        } catch (\Exception $e) {
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
