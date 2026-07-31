<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class StoreView extends Column
{
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = '<div style="font-size:12px; color:#555;">Main Website<br/><span style="color:#777;">Default Store View</span></div>';
            }
        }
        return $dataSource;
    }
}
