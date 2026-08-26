<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\AgentService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class AgentChatResolver implements ResolverInterface
{
    public function __construct(
        private AgentService $agent,
        private CustomerContext $customerContext
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $input = is_array($args['input'] ?? null) ? $args['input'] : [];
        if (trim((string)($input['message'] ?? '')) === '') {
            throw new GraphQlInputException(__('The message is required.'));
        }
        $clientContext = [
            // session_id is retained only as a legacy alias for the anonymous client id.
            'client_id' => isset($input['client_id']) ? (string)$input['client_id'] : (isset($input['session_id']) ? (string)$input['session_id'] : null),
            'conversation_id' => isset($input['conversation_id']) ? (string)$input['conversation_id'] : null,
            'cart_id' => isset($input['cart_id']) ? (string)$input['cart_id'] : null,
            'filters' => is_array($input['current_filters'] ?? null) ? $input['current_filters'] : [],
            'page_url' => (string)($input['page_url'] ?? ''),
            'query_phrase' => (string)($input['query_phrase'] ?? ''),
        ];
        try {
            return $this->agent->chatWithIdentity(
                (string)$input['message'],
                $clientContext,
                $this->customerContext->getCustomerId($context),
                'graphql'
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }
    }
}
