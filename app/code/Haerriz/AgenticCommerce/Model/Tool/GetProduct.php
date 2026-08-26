<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Haerriz\AgenticCommerce\Model\ProductPresenter;
use Magento\Store\Model\StoreManagerInterface;

class GetProduct implements ToolInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductPresenter $productPresenter,
        private StoreManagerInterface $storeManager
    ) {
    }

    public function getName(): string
    {
        return 'get_product';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Fetch a known product by exact SKU. Never invent the SKU.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['sku' => ['type' => 'string']],
                    'required' => ['sku'],
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $sku = trim((string)($arguments['sku'] ?? ''));
        if ($sku === '') {
            return ['assistant_message' => (string)__('A SKU is required.')];
        }
        try {
            $product = $this->productRepository->get($sku, false, (int)$this->storeManager->getStore()->getId());
            return [
                'products' => [$this->productPresenter->present($product)],
                'total_count' => 1,
                'actions' => [['type' => 'navigate', 'label' => (string)__('Open product'), 'url' => $product->getProductUrl()]],
                'assistant_message' => (string)__('Here is the product I found.'),
            ];
        } catch (\Throwable) {
            return ['products' => [], 'total_count' => 0, 'assistant_message' => (string)__('I could not find that product.')];
        }
    }
}
