<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class JobActions extends Column
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
        foreach ($dataSource['data']['items'] ?? [] as &$item) {
            $id = (int)$item['job_id'];
            $item[$this->getData('name')] = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('*/*/view', ['id' => $id]),
                    'label' => __('View Details'),
                ],
                'download' => [
                    'href' => $this->urlBuilder->getUrl('*/*/download', ['id' => $id]),
                    'label' => __('Download'),
                ],
                'retry' => [
                    'href' => $this->urlBuilder->getUrl('*/*/retry', ['id' => $id]),
                    'label' => __('Retry'),
                    'confirm' => ['title' => __('Retry job'), 'message' => __('Generate this profile again?')],
                    'post' => true,
                ],
                'cancel' => [
                    'href' => $this->urlBuilder->getUrl('*/*/cancel', ['id' => $id]),
                    'label' => __('Cancel'),
                    'post' => true,
                ],
            ];
        }
        unset($item);
        return $dataSource;
    }
}
