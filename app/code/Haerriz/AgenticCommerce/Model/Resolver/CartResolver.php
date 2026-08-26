<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Cart\CartService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Identity\IdentityResolver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartResolver implements ResolverInterface
{
    public function __construct(private CartService $cartService, private IdentityResolver $identityResolver, private CustomerContext $customerContext) {}
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $identity = $this->customerContext->identityForTool($context, $args['client_id'] ?? null, 'get_cart');
        try {
            return $this->cartService->getSummary($identity, isset($args['cart_id']) ? (string)$args['cart_id'] : null);
        } catch (AuthorizationException $e) {
            throw new GraphQlAuthorizationException(__($e->getMessage()));
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }
    }
}
