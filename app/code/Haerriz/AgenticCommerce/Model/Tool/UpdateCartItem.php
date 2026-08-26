<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Cart\CartService;

class UpdateCartItem implements ToolInterface
{
    public function __construct(private CartService $cartService) {}

    public function getName(): string { return 'update_cart_item'; }

    public function getDefinition(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $this->getName(),
            'description' => 'Change quantity for a cart item by its 1-based visible position. Never supply Magento quote item ids.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'description' => '1-based item position; 0 means the last visible item'],
                    'quantity' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                ],
                'required' => ['index', 'quantity'],
            ],
        ]];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $identity = (array)($context['identity'] ?? []);
        $cartId = $context['cart_id'] ?? null;
        $summary = $this->cartService->getSummary($identity, $cartId);
        $requestedIndex = (int)($arguments['index'] ?? 1);
        $index = $requestedIndex === 0 ? count((array)($summary['items'] ?? [])) : max(1, $requestedIndex);
        $itemId = (int)($summary['items'][$index - 1]['item_id'] ?? 0);
        if ($itemId <= 0) {
            return [
                'cart' => $summary,
                'assistant_message' => (string)__('I could not find cart item #%1.', $index),
            ];
        }
        return $this->cartService->updateItem(
            $identity,
            $itemId,
            (float)($arguments['quantity'] ?? 1),
            $cartId
        );
    }
}
