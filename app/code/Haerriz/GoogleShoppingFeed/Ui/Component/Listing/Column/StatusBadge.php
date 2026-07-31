<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class StatusBadge extends Column
{
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $status = (int)($item['status'] ?? 0);
                if ($status === 1) {
                    $html = '<span style="background:#e8f5e9; color:#2e7d32; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:bold; display:inline-block;">Active</span>';
                } else {
                    $html = '<span style="background:#ffebee; color:#c62828; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:bold; display:inline-block;">Inactive</span>';
                }
                $item[$this->getData('name')] = $html;
            }
        }
        return $dataSource;
    }
}
