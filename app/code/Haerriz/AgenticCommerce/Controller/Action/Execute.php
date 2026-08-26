<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Controller\Action;

use Haerriz\AgenticCommerce\Model\DirectActionService;
use Haerriz\AgenticCommerce\Model\InputSanitizer;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Execute extends Action implements HttpPostActionInterface
{
    public function __construct(Context $context, private JsonFactory $jsonFactory, private DirectActionService $actions, private InputSanitizer $sanitizer) { parent::__construct($context); }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $context = $this->sanitizer->context($this->getRequest()->getParam('context') !== null ? (string)$this->getRequest()->getParam('context') : null);
            $idempotencyKey=mb_substr(trim((string)$this->getRequest()->getParam('idempotency_key','')),0,128);
            if($idempotencyKey!=='')$context['idempotency_key']=$idempotencyKey;
            $raw = (string)$this->getRequest()->getParam('arguments', '{}');
            $arguments = json_decode($raw, true); if (!is_array($arguments)) $arguments = [];
            $payload = $this->actions->execute(mb_substr(trim((string)$this->getRequest()->getParam('action', '')), 0, 64), $arguments, $context);
            return $result->setData(['success' => true, 'data' => $payload]);
        } catch (LocalizedException $e) {
            $result->setHttpResponseCode(400); return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            $result->setHttpResponseCode(500); return $result->setData(['success' => false, 'message' => (string)__('The storefront action could not be completed.')]);
        }
    }
}
