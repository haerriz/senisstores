<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationActionRegistry;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/** Legacy typed order-confirmation resolver. Generic confirmations use ConfirmPendingActionResolver. */
class ConfirmActionResolver implements ResolverInterface
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
        if ((string)($pending['action'] ?? '') !== 'place_order') {
            throw new GraphQlInputException(__('This legacy mutation only supports order placement. Use agenticCommerceConfirmPendingAction for other confirmations.'));
        }
        $handler = $this->registry->get('place_order');
        if ($handler === null) throw new GraphQlInputException(__('Order confirmation is not available.'));
        return $handler->execute((array)($pending['payload'] ?? []), $identity, ['cart_id' => $input['cart_id'] ?? null, 'identity' => $identity]);
    }
}
