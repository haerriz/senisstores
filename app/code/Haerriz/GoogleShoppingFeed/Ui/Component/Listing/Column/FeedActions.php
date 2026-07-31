<?php
namespace Haerriz\GoogleShoppingFeed\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

class FeedActions extends Column
{
    private $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
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
                $id = (int)($item['profile_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = $item['name'] ?? __('Feed Profile');
                $item[$this->getData('name')] = [
                    'edit' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/edit', ['id' => $id]),
                        'label' => __('Edit')
                    ],
                    'quick_view' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/preview', ['id' => $id]),
                        'label' => __('Quick View'),
                        'target' => '_blank'
                    ],
                    'generate' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/trigger', ['action' => 'run', 'id' => $id]),
                        'label' => __('Generate Now'),
                        'confirm' => [
                            'title' => __('Generate Feed'),
                            'message' => __('Generate feed now for profile "%1"?', $name)
                        ]
                    ],
                    'duplicate' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/duplicate', ['id' => $id]),
                        'label' => __('Duplicate')
                    ],
                    'history' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/job/index', ['filters' => ['profile_id' => $id]]),
                        'label' => __('Job History')
                    ],
                    'download' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/download', ['id' => $id]),
                        'label' => __('Download Feed')
                    ],
                    'delete' => [
                        'href' => $this->urlBuilder->getUrl('haerriz_googleshoppingfeed/feed/delete', ['id' => $id]),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete Profile'),
                            'message' => __('Are you sure you want to delete profile "%1"?', $name)
                        ]
                    ]
                ];
            }
        }
        return $dataSource;
    }
}
