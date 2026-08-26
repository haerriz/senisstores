<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Controller\Chat;

use Haerriz\AgenticCommerce\Model\AgentService;
use Haerriz\AgenticCommerce\Model\Action\IdempotencyService;
use Haerriz\AgenticCommerce\Model\InputSanitizer;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Message extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private AgentService $agent,
        private InputSanitizer $sanitizer,
        private IdempotencyService $idempotency,
        private StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $idempotencyKey = '';
        $scope = '';
        $storeId = 0;
        $reservationOwned = false;
        try {
            $clientContext = $this->sanitizer->context(
                $this->getRequest()->getParam('context') !== null
                    ? (string)$this->getRequest()->getParam('context')
                    : null
            );
            // Keep the old session_id request parameter only as a client-id alias.
            if (empty($clientContext['client_id']) && $this->getRequest()->getParam('session_id') !== null) {
                $clientContext['client_id'] = (string)$this->getRequest()->getParam('session_id');
            }
            $idempotencyKey = mb_substr(trim((string)$this->getRequest()->getParam('idempotency_key', '')), 0, 128);
            $storeId = (int)$this->storeManager->getStore()->getId();
            $scope = 'storefront-chat:' . hash('sha256', (string)($clientContext['client_id'] ?? ''));
            $fingerprint = $this->idempotency->fingerprint([
                'message' => (string)$this->getRequest()->getParam('message', ''),
                'context' => $clientContext,
                'store_id' => $storeId,
            ]);
            $cached = $this->idempotency->acquire($idempotencyKey, $scope, 'agent_chat', $fingerprint, $storeId);
            if (is_array($cached)) {
                return $result->setData(['success' => true, 'data' => $cached]);
            }
            $reservationOwned = $idempotencyKey !== '';
            $payload = $this->agent->chatWithIdentity(
                (string)$this->getRequest()->getParam('message', ''),
                $clientContext,
                null,
                'storefront'
            );
            $this->idempotency->complete($idempotencyKey, $scope, $payload, $storeId);
            $reservationOwned = false;
            return $result->setData(['success' => true, 'data' => $payload]);
        } catch (LocalizedException $e) {
            if ($reservationOwned && $idempotencyKey !== '' && $scope !== '') {
                $this->idempotency->abandon($idempotencyKey, $scope, $storeId);
            }
            $result->setHttpResponseCode(400);
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            if ($reservationOwned && $idempotencyKey !== '' && $scope !== '') {
                // A tool may have committed before an unexpected failure. Keep the reservation
                // uncertain so a transport retry cannot duplicate a cart/account mutation.
                $this->idempotency->markUncertain($idempotencyKey, $scope, $storeId);
            }
            $result->setHttpResponseCode(500);
            return $result->setData(['success' => false, 'message' => (string)__('The shopping assistant is temporarily unavailable.')]);
        }
    }
}
