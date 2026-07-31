<?php
namespace Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\PreviewService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Preview extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::preview';

    private $repository;
    private $previewService;

    public function __construct(
        Context $context,
        FeedProfileRepositoryInterface $repository,
        PreviewService $previewService
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->previewService = $previewService;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        try {
            $profile = $this->repository->getById($id);
            $sampleRows = $this->previewService->buildSample($profile, 10);
            
            $ext = strtolower(pathinfo($profile->getFilename(), PATHINFO_EXTENSION));
            if ($ext === 'csv' || $ext === 'tsv') {
                $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
                $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
                if (!empty($sampleRows)) {
                    $output = implode(',', array_keys($sampleRows[0])) . "\n";
                    foreach ($sampleRows as $row) {
                        $output .= implode(',', array_map(function($v) { return '"' . str_replace('"', '""', (string)$v) . '"'; }, $row)) . "\n";
                    }
                    $response->setContents($output);
                } else {
                    $response->setContents("No product sample rows available.");
                }
                return $response;
            } elseif ($ext === 'jsonl' || $ext === 'json') {
                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData(['status' => 'success', 'profile' => $profile->getName(), 'rows' => $sampleRows]);
                return $response;
            } else {
                // XML format default
                $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
                $response->setHeader('Content-Type', 'text/xml; charset=UTF-8');
                $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\n<channel>\n";
                $xml .= "<title>" . htmlspecialchars($profile->getName()) . "</title>\n";
                foreach ($sampleRows as $row) {
                    $xml .= "  <item>\n";
                    foreach ($row as $k => $v) {
                        $tag = htmlspecialchars($k);
                        $val = htmlspecialchars((string)$v);
                        $xml .= "    <{$tag}>{$val}</{$tag}>\n";
                    }
                    $xml .= "  </item>\n";
                }
                $xml .= "</channel>\n</rss>";
                $response->setContents($xml);
                return $response;
            }
        } catch (\Exception $e) {
            $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
            $response->setContents("Error generating quick view preview: " . $e->getMessage());
            return $response;
        }
    }
}
