<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\GraphQl;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Identity\IdentityResolver;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * Trusted identity bridge for the module's GraphQL surface.
 *
 * Customer ids only come from Magento's GraphQL context. Client ids remain anonymous correlation
 * identifiers and can never be promoted to a customer identity. Tool policy is enforced here so a
 * headless client cannot bypass Admin feature toggles or customer-only capability gates by calling
 * a resolver directly.
 */
class CustomerContext
{
    public function __construct(
        private IdentityResolver $identityResolver,
        private ToolPolicy $toolPolicy
    ) {
    }

    /** Return a trusted Magento customer id from GraphQL context, never from GraphQL arguments. */
    public function getCustomerId(mixed $context): ?int
    {
        try {
            if (!is_object($context)) {
                return null;
            }
            if (method_exists($context, 'getUserType')) {
                $type = (int)$context->getUserType();
                if ($type !== 0 && $type !== UserContextInterface::USER_TYPE_CUSTOMER) {
                    return null;
                }
            }
            if (method_exists($context, 'getExtensionAttributes')) {
                $extensions = $context->getExtensionAttributes();
                if ($extensions && method_exists($extensions, 'getIsCustomer') && !$extensions->getIsCustomer()) {
                    return null;
                }
            }
            if (method_exists($context, 'getUserId')) {
                $id = (int)$context->getUserId();
                return $id > 0 ? $id : null;
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    public function identity(mixed $context, ?string $clientId = null): array
    {
        return $this->identityResolver->resolve($this->getCustomerId($context), $clientId, 'graphql');
    }

    /**
     * Resolve identity and enforce the same tool policy used by chat/direct actions.
     * GraphQL-specific exceptions intentionally avoid leaking internal authorization details.
     */
    public function identityForTool(mixed $context, ?string $clientId, string $toolName): array
    {
        $identity = $this->identity($context, $clientId);
        try {
            $this->toolPolicy->assertAllowed($toolName, $identity);
        } catch (AuthorizationException $e) {
            throw new GraphQlAuthorizationException(__($e->getMessage()));
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }
        return $identity;
    }
}
