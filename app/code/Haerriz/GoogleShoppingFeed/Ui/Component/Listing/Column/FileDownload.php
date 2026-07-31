<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class FileDownload extends Column
{
    private $urlBuilder;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $id = $item['profile_id'] ?? 0;
                $filename = $item['filename'] ?? 'feed.xml';
                $downloadUrl = $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/download', ['id' => $id]);
                
                $html = '<a href="' . $downloadUrl . '" style="color:#007bdb; text-decoration:none; font-weight:600;" target="_blank">';
                $html .= '📥 ' . htmlspecialchars($filename) . '</a>';
                $item[$this->getData('name')] = $html;
            }
        }
        return $dataSource;
    }
}
