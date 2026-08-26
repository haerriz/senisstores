<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action\Handler;

use Haerriz\AgenticCommerce\Api\DirectActionHandlerInterface;
use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;
use Haerriz\AgenticCommerce\Model\Customer\CustomerAccountService;
use Magento\Framework\Exception\LocalizedException;

class PrepareDeleteCustomerAddress implements DirectActionHandlerInterface
{
    public function __construct(private CustomerAccountService $customers, private ConfirmationService $confirmations) {}
    public function action(): string { return 'prepare_delete_customer_address'; }
    public function toolName(): string { return 'prepare_delete_saved_address'; }
    public function label(array $arguments): string { return (string)__('Delete saved address'); }
    public function sanitize(array $arguments): array
    {
        $id = (int)($arguments['address_id'] ?? 0);
        if ($id <= 0) throw new LocalizedException(__('Choose a saved address.'));
        return ['address_id' => $id];
    }
    public function execute(array $arguments, array $identity, array $context = []): array
    {
        $conversationId = (string)($context['conversation_id'] ?? '');
        if ($conversationId === '') throw new LocalizedException(__('Conversation context is required.'));
        $owned = $this->customers->ownedAddress($identity, (int)$arguments['address_id']);
        $summary = (string)__('Delete the saved address in %1, %2?', (string)$owned['city'], (string)$owned['postcode']);
        return [
            'confirmation' => $this->confirmations->create($conversationId, $identity, 'delete_customer_address', ['address_id' => (int)$arguments['address_id']], $summary),
            'assistant_message' => $summary,
        ];
    }
}
