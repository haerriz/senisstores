<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class OpenRecentProduct implements ToolInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private StoreManagerInterface $storeManager
    ) {
    }

    public function getName(): string { return 'open_recent_product'; }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Open one of the products from the immediately previous search result by 1-based position.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['index' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24]],
                    'required' => ['index'],
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $index = max(1, (int)($arguments['index'] ?? 1));
        $recent = is_array($context['recent_products'] ?? null) ? $context['recent_products'] : [];
        $sku = trim((string)($recent[$index - 1]['sku'] ?? ''));
        if ($sku === '') {
            return ['assistant_message' => (string)__('I no longer have that product in the current result context.')];
        }
        try {
            $product = $this->productRepository->get($sku, false, (int)$this->storeManager->getStore()->getId());
            return [
                'actions' => [['type' => 'navigate', 'label' => (string)__('Open %1', $product->getName()), 'url' => $product->getProductUrl(), 'auto_navigate' => true]],
                'assistant_message' => (string)__('I found that product.'),
            ];
        } catch (\Throwable) {
            return ['assistant_message' => (string)__('That product is no longer available.')];
        }
    }
}
