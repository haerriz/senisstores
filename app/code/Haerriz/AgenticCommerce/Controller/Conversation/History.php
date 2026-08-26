<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Controller\Conversation;

use Haerriz\AgenticCommerce\Model\Conversation\ConversationService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class History extends Action implements HttpGetActionInterface
{
    public function __construct(Context $context, private JsonFactory $jsonFactory, private ConversationService $service)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $clientId = $this->getRequest()->getParam('client_id') !== null ? (string)$this->getRequest()->getParam('client_id') : null;
            $conversationId = trim((string)$this->getRequest()->getParam('conversation_id', ''));
            $data = $conversationId !== ''
                ? $this->service->get($conversationId, $clientId, null, 'storefront')
                : $this->service->list($clientId, null, (int)$this->getRequest()->getParam('limit', 30), 1, 'storefront');
            return $result->setData(['success' => true, 'data' => $data]);
        } catch (LocalizedException $e) {
            $result->setHttpResponseCode(400);
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            $result->setHttpResponseCode(500);
            return $result->setData(['success' => false, 'message' => (string)__('Unable to load conversation history.')]);
        }
    }
}
