<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Cart\CartService;

class AddRecentProductToCart implements ToolInterface
{
    public function __construct(private CartService $cartService) {}
    public function getName(): string { return 'add_recent_product_to_cart'; }
    public function getDefinition(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $this->getName(),
            'description' => 'Add a product from the most recently shown server-side product result to the shopper cart by 1-based position.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24],
                    'quantity' => ['type' => 'number', 'minimum' => 1, 'maximum' => 100],
                ],
                'required' => ['index'],
            ],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $recent = is_array($context['recent_products'] ?? null) ? $context['recent_products'] : [];
        $index = max(1, (int)($arguments['index'] ?? 1));
        $sku = trim((string)($recent[$index - 1]['sku'] ?? ''));
        if ($sku === '') {
            return ['assistant_message' => (string)__('I no longer have that product in this conversation. Search for products again and then ask me to add one.')];
        }
        return $this->cartService->addProduct(
            (array)$context['identity'],
            $sku,
            (float)($arguments['quantity'] ?? 1),
            $context['cart_id'] ?? null
        );
    }
}
