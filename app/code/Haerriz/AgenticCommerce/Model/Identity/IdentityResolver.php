<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Identity;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;

class IdentityResolver
{
    public function __construct(
        private UserContextInterface $userContext,
        private CustomerSession $customerSession,
        private StoreManagerInterface $storeManager,
        private Random $random,
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * Resolve shopper identity only from trusted Magento auth context plus an opaque anonymous client id.
     * The client id is never accepted as a customer identity.
     */
    public function resolve(?int $trustedCustomerId = null, ?string $clientId = null, string $channel = 'storefront'): array
    {
        $customerId = max(0, (int)$trustedCustomerId);
        if ($customerId === 0 && $this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER) {
            $customerId = max(0, (int)$this->userContext->getUserId());
        }
        if ($customerId === 0 && $channel === 'storefront' && $this->customerSession->isLoggedIn()) {
            $customerId = max(0, (int)$this->customerSession->getCustomerId());
        }

        $clientId = $this->normalizeClientId($clientId);
        if ($clientId === '') {
            $clientId = $this->random->getRandomString(48);
        }

        $customerGroupId = 0;
        if ($customerId > 0) {
            try {
                $customerGroupId = max(0, (int)$this->customerRepository->getById($customerId)->getGroupId());
            } catch (\Throwable) {
                // Authentication identity remains authoritative. Pricing services can safely fall back
                // to Magento's default group context if the customer record disappears mid-request.
                $customerGroupId = 0;
            }
        }

        return [
            'customer_id' => $customerId,
            'customer_group_id' => $customerGroupId,
            'client_id' => $clientId,
            'store_id' => (int)$this->storeManager->getStore()->getId(),
            'channel' => $channel,
            'is_customer' => $customerId > 0,
        ];
    }

    public function normalizeClientId(?string $clientId): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$clientId) ?: '';
        if (strlen($value) < 20 || strlen($value) > 80) {
            return '';
        }
        return $value;
    }
}
