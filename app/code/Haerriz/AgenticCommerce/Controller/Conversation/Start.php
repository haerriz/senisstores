<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Controller\Conversation;

use Haerriz\AgenticCommerce\Model\Conversation\ConversationService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Start extends Action implements HttpPostActionInterface
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
            return $result->setData(['success' => true, 'data' => $this->service->start($clientId, null, 'storefront')]);
        } catch (\Throwable $e) {
            $result->setHttpResponseCode(500);
            return $result->setData(['success' => false, 'message' => (string)__('Unable to start a new conversation.')]);
        }
    }
}
