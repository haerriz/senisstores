<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Conversation\ConversationService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ConversationResolver implements ResolverInterface
{
    public function __construct(private ConversationService $service, private CustomerContext $customerContext) {}
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $id = trim((string)($args['id'] ?? ''));
        if ($id === '') {
            throw new GraphQlInputException(__('Conversation id is required.'));
        }
        try {
            return $this->service->get($id, isset($args['client_id']) ? (string)$args['client_id'] : null, $this->customerContext->getCustomerId($context), 'graphql');
        } catch (AuthorizationException $e) {
            throw new GraphQlAuthorizationException(__($e->getMessage()));
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }
    }
}
