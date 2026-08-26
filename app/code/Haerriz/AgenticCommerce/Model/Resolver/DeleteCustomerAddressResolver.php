<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Haerriz\AgenticCommerce\Model\Conversation\ConversationRepository;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Security-preserving compatibility resolver: deletion is prepared, never executed immediately.
 * The returned confirmation must be consumed by the generic confirmation mutation.
 */
class DeleteCustomerAddressResolver implements ResolverInterface
{
    public function __construct(
        private CustomerAccountService $customers,
        private CustomerContext $customerContext,
        private ConfirmationService $confirmations,
        private ConversationRepository $conversations
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $identity = $this->customerContext->identityForTool($context, $args['client_id'] ?? null, 'prepare_delete_saved_address');
        $address = $this->customers->ownedAddress($identity, (int)($args['address_id'] ?? 0));
        $conversation = $this->conversations->getOrStart(isset($args['conversation_id']) ? (string)$args['conversation_id'] : null, $identity);
        $summary = (string)__('Delete the saved address in %1, %2?', (string)($address['city'] ?? ''), (string)($address['postcode'] ?? ''));
        $confirmation = $this->confirmations->create(
            (string)$conversation['public_id'],
            $identity,
            'delete_customer_address',
            ['address_id' => (int)$address['id']],
            $summary
        );
        return [
            'saved' => false,
            'deleted' => false,
            'address' => $address,
            'addresses' => $this->customers->addressList($identity),
            'confirmation' => $confirmation,
            'assistant_message' => $summary . ' ' . (string)__('Confirm this action to continue.'),
        ];
    }
}
