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
    const ADMIN_RESOURCE = 'Haerriz_GoogleShoppingFeed::generate';

    private FeedProfileRepositoryInterface $repository;
    private PreviewService $previewService;

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
        if ($id <= 0) {
            return $this->errorResponse('Missing or invalid profile id.');
        }

        try {
            $profile = $this->repository->getById($id);
            $sample = $this->previewService->buildSample($profile, 10);
            $sampleRows = is_array($sample['rows'] ?? null) ? $sample['rows'] : [];
            $ext = strtolower((string)pathinfo((string)$profile->getFilename(), PATHINFO_EXTENSION));

            if (in_array($ext, ['csv', 'tsv', 'txt'], true)) {
                $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
                $delimiter = $ext === 'tsv' ? "\t" : ',';
                $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
                if (!$sampleRows) {
                    $response->setContents('No product sample rows available.');
                    return $response;
                }
                $output = implode($delimiter, array_map([$this, 'stringifyValue'], array_keys($sampleRows[0]))) . "\n";
                foreach ($sampleRows as $row) {
                    $output .= implode($delimiter, array_map(static function ($v) {
                        if (is_array($v) || is_object($v)) {
                            $encoded = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $v = $encoded === false ? '' : $encoded;
                        }
                        return '"' . str_replace('"', '""', (string)$v) . '"';
                    }, $row)) . "\n";
                }
                $response->setContents($output);
                return $response;
            }

            if (in_array($ext, ['jsonl', 'json'], true)) {
                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData([
                    'status' => 'success',
                    'profile' => $profile->getName(),
                    'count' => (int)($sample['row_count'] ?? count($sampleRows)),
                    'rows' => $sampleRows,
                    'field_errors' => $sample['field_errors'] ?? [],
                    'completeness' => $sample['completeness'] ?? [],
                ]);
                return $response;
            }

            $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $response->setHeader('Content-Type', 'text/xml; charset=UTF-8');
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $xml .= "<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\n<channel>\n";
            $xml .= '<title>' . htmlspecialchars((string)$profile->getName(), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
            foreach ($sampleRows as $row) {
                $xml .= "  <item>\n";
                foreach ($row as $k => $v) {
                    $tag = preg_replace('/[^A-Za-z0-9_:\-.]/', '', (string)$k) ?: 'field';
                    $val = htmlspecialchars($this->stringifyValue($v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml .= "    <{$tag}>{$val}</{$tag}>\n";
                }
                $xml .= "  </item>\n";
            }
            $xml .= "</channel>\n</rss>";
            $response->setContents($xml);
            return $response;
        } catch (\Throwable $e) {
            return $this->errorResponse('Error generating quick view preview: ' . $e->getMessage());
        }
    }

    private function errorResponse(string $message)
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->setHttpResponseCode(400);
        $response->setContents($message);
        return $response;
    }

    private function stringifyValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? '' : $encoded;
        }
        return (string)$value;
    }
}
