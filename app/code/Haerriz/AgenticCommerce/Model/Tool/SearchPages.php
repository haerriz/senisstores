<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;

class SearchPages implements ToolInterface
{
    public function __construct(private KnowledgeService $knowledgeService) {}

    public function getName(): string { return 'search_pages'; }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Find active Magento CMS pages, block headings, or block links and return safe storefront navigation actions.',
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
        if ($query === '') return ['assistant_message' => (string)__('Tell me which page you want.')];
        $limit = max(1, min(5, (int)($arguments['limit'] ?? 5)));
        $items = $this->knowledgeService->search($query, $limit);
        $queryKey = $this->normalizeLabel($query);
        $exactItems = array_values(array_filter($items, function (array $item) use ($queryKey): bool {
            $identifier = preg_replace('/^block:/u', '', (string)($item['identifier'] ?? '')) ?? '';
            return $this->normalizeLabel((string)($item['title'] ?? '')) === $queryKey
                || $this->normalizeLabel($identifier) === $queryKey;
        }));
        if ($exactItems !== []) {
            $items = $exactItems;
        }
        $actions = [];
        foreach ($items as $item) {
            $actions[] = [
                'type' => 'navigate',
                'label' => (string)$item['title'],
                'url' => (string)$item['url'],
            ];
        }
        // The planner invokes this tool only after an exact active CMS label/identifier match.
        if ($actions !== []) {
            $actions[0]['auto_navigate'] = true;
        }
        return [
            'knowledge' => $items,
            'actions' => $actions,
            'assistant_message' => $actions ? (string)__('I found these matching pages.') : (string)__('I could not find a matching page.'),
        ];
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
