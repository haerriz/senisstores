<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Magento\Framework\UrlInterface;

class Navigate implements ToolInterface
{
    private const TARGETS = [
        'home' => ['', 'Home'],
        'cart' => ['checkout/cart', 'Cart'],
        'checkout' => ['checkout', 'Checkout'],
        'account' => ['customer/account', 'My Account'],
        'wishlist' => ['wishlist', 'Wishlist'],
        'orders' => ['sales/order/history', 'My Orders'],
        'login' => ['customer/account/login', 'Sign In'],
        'register' => ['customer/account/create', 'Create Account'],
        'forgot_password' => ['customer/account/forgotpassword', 'Reset Password'],
    ];

    public function __construct(private UrlInterface $urlBuilder)
    {
    }

    public function getName(): string
    {
        return 'navigate';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => 'Create a safe navigation action for a fixed storefront destination.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['target' => ['type' => 'string', 'enum' => array_keys(self::TARGETS)]],
                    'required' => ['target'],
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $target = (string)($arguments['target'] ?? '');
        if (!isset(self::TARGETS[$target])) {
            return ['assistant_message' => (string)__('That destination is not available through the assistant.')];
        }
        [$route, $label] = self::TARGETS[$target];
        return [
            'actions' => [[
                'type' => 'navigate',
                'label' => (string)__($label),
                'url' => $route === '' ? $this->urlBuilder->getBaseUrl() : $this->urlBuilder->getUrl($route),
                'auto_navigate' => true,
            ]],
            'assistant_message' => (string)__('Use the button below to continue.'),
        ];
    }
}
