<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Cart\CartService;

class AddProductToCart implements ToolInterface
{
    public function __construct(private CartService $cartService) {}

    public function getName(): string
    {
        return 'add_product_to_cart';
    }

    public function getDefinition(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $this->getName(),
            'description' => 'Add a product by an exact SKU explicitly supplied by the shopper. Never invent a SKU.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'sku' => ['type' => 'string', 'maxLength' => 64],
                    'quantity' => ['type' => 'number', 'minimum' => 1, 'maximum' => 100],
                    'selections' => ['type'=>'array','items'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'values'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['code','values']]],
                ],
                'required' => ['sku'],
            ],
        ]];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $sku = mb_substr(trim((string)($arguments['sku'] ?? '')), 0, 64);
        if ($sku === '') {
            return ['assistant_message' => (string)__('Tell me the exact SKU you want to add.')];
        }
        return $this->cartService->addProduct(
            (array)($context['identity'] ?? []),
            $sku,
            (float)($arguments['quantity'] ?? 1),
            isset($context['cart_id']) ? (string)$context['cart_id'] : null,
            is_array($arguments['selections'] ?? null) ? $arguments['selections'] : []
        );
    }
}
