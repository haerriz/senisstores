<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Cart\CartService;

class ClearCart implements ToolInterface
{
    public function __construct(private CartService $cartService) {}

    public function getName(): string { return 'clear_cart'; }

    public function getDefinition(): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $this->getName(),
            'description' => 'Remove all items from the current authorized shopper cart.',
            'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
        ]];
    }

    public function execute(array $arguments, array $context = []): array
    {
        return $this->cartService->clear((array)$context['identity'], $context['cart_id'] ?? null);
    }
}
