<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class SearchCategories implements ToolInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private StoreManagerInterface $storeManager
    ) {
    }

    public function getName(): string
    {
        return 'search_categories';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Find active Magento categories by name and return safe navigation actions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            return ['assistant_message' => (string)__('Tell me which category you want.')];
        }
        $limit = max(1, min(10, (int)($arguments['limit'] ?? 5)));
        $store = $this->storeManager->getStore();
        $collection = $this->collectionFactory->create();
        $collection->setStoreId((int)$store->getId())
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('name', ['like' => '%' . addcslashes($query, '%_') . '%'])
            ->setPageSize($limit);
        $actions = [];
        foreach ($collection as $category) {
            $actions[] = [
                'type' => 'navigate',
                'label' => (string)$category->getName(),
                'url' => (string)$category->getUrl(),
            ];
        }
        if (count($actions) === 1) {
            $actions[0]['auto_navigate'] = true;
        }
        return [
            'actions' => $actions,
            'assistant_message' => $actions !== [] ? (string)__('I found these matching sections.') : (string)__('I could not find a matching category.'),
        ];
    }
}
