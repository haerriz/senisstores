<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationActionRegistry;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/** Generic confirmation resolver for enterprise extension actions. */
class ConfirmPendingActionResolver implements ResolverInterface
{
    public function __construct(
        private ConfirmationService $confirmations,
        private ConfirmationActionRegistry $registry,
        private CustomerContext $customerContext
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, $input['client_id'] ?? null, 'confirm_pending_action');
        $pending = $this->confirmations->consume((string)($input['token'] ?? ''), $identity);
        $action = (string)($pending['action'] ?? '');
        $handler = $this->registry->get($action);
        if ($handler === null) throw new GraphQlInputException(__('Unsupported confirmation action.'));
        $result = $handler->execute((array)($pending['payload'] ?? []), $identity, ['cart_id' => $input['cart_id'] ?? null, 'identity' => $identity]);
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return [
            'action' => $action,
            'assistant_message' => (string)($result['assistant_message'] ?? __('Confirmed action completed.')),
            'result_json' => is_string($json) ? $json : '{}',
        ];
    }
}
