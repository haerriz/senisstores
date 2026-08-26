<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Search\SearchAdapterInterface;

class SearchProducts implements ToolInterface
{
    public function __construct(private SearchAdapterInterface $searchService)
    {
    }

    public function getName(): string
    {
        return 'search_products';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Search the Magento catalog and apply validated product attribute filters.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'phrase' => ['type' => 'string'],
                        'filters' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'attribute' => ['type' => 'string'],
                                    'condition' => ['type' => 'string', 'enum' => ['eq', 'in', 'nin', 'match', 'from', 'to', 'range']],
                                    'values' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'label' => ['type' => 'string'],
                                ],
                                'required' => ['attribute', 'values'],
                            ],
                        ],
                        'sort' => [
                            'type' => 'object',
                            'properties' => [
                                'attribute' => ['type' => 'string', 'enum' => ['price', 'name', 'created_at', 'bestseller']],
                                'direction' => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                            ],
                        ],
                        'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24],
                        'current_page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $arguments['_customer_group_id'] = max(0, (int)($context['identity']['customer_group_id'] ?? 0));
        $result = $this->searchService->search($arguments);
        $shown = count((array)($result['products'] ?? []));
        $total = (int)($result['total_count'] ?? 0);
        if ($total > 0) {
            $result['assistant_message'] = $shown < $total
                ? (string)__('Showing %1 of %2 matching products. You can refine the results in plain language.', $shown, $total)
                : (string)__('I found %1 matching product(s). You can refine the results in plain language.', $total);
        } else {
            $result['assistant_message'] = (string)__('I could not find a product matching that request. Try a different product phrase or relax one filter.');
        }
        return $result;
    }
}
