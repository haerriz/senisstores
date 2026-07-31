<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class GenerationSummary extends Column
{
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $updatedAt = $item['updated_at'] ?? null;
                if ($updatedAt) {
                    $html = '<div><strong>Status:</strong> <span style="color:#2e7d32;">Ready</span><br/>';
                    $html .= '<span style="font-size:11px; color:#666;">Date: ' . htmlspecialchars($updatedAt) . '</span><br/>';
                    $html .= '<span style="font-size:11px; color:#666;">Executed: Manually</span></div>';
                } else {
                    $html = '<span style="color:#888; font-style:italic;">Status: Not yet Generated</span>';
                }
                $item[$this->getData('name')] = $html;
            }
        }
        return $dataSource;
    }
}
