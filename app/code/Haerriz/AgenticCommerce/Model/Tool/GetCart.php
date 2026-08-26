<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Cart\CartService;

class GetCart implements ToolInterface
{
    public function __construct(private CartService $cartService) {}
    public function getName(): string { return 'get_cart'; }
    public function getDefinition(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $this->getName(),
            'description' => 'Read the current shopper cart. Cart identity is taken from trusted server context.',
            'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $cart = $this->cartService->getSummary((array)$context['identity'], $context['cart_id'] ?? null);
        $message = $cart['items_count'] > 0
            ? (string)__('Your cart has %1 item(s), subtotal %2.', $cart['items_count'], $cart['formatted_subtotal'])
            : (string)__('Your cart is empty.');
        return ['cart' => $cart, 'assistant_message' => $message];
    }
}
