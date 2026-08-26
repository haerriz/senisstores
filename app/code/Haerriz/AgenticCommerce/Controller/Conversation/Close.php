<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Controller\Conversation;

use Haerriz\AgenticCommerce\Model\Conversation\ConversationService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Close extends Action implements HttpPostActionInterface
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
            return $result->setData([
                'success' => true,
                'data' => $this->service->close($conversationId, $clientId, null, 'storefront'),
            ]);
        } catch (LocalizedException $e) {
            $result->setHttpResponseCode(400);
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            $result->setHttpResponseCode(500);
            return $result->setData(['success' => false, 'message' => (string)__('Unable to close the conversation.')]);
        }
    }
}
