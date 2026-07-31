<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class ExecutionMode extends Column
{
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $cron = trim($item['cron_expr'] ?? '');
                $mode = (!empty($cron)) ? 'By Schedule' : 'Manually';
                $item[$this->getData('name')] = '<span style="font-weight:500;">' . $mode . '</span>';
            }
        }
        return $dataSource;
    }
}
