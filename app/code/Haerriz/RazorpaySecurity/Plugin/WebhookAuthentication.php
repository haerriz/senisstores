<?php

declare(strict_types=1);

namespace Haerriz\RazorpaySecurity\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RawFactory;
use Razorpay\Magento\Controller\Payment\Webhook;
use Razorpay\Magento\Model\Config;

final class WebhookAuthentication
{
    public function __construct(
        private readonly Config $config,
        private readonly Http $request,
        private readonly RawFactory $rawFactory
    ) {
    }

    public function aroundExecute(Webhook $subject, callable $proceed)
    {
        if (!$this->request->isPost()) {
            return $this->rawFactory->create()->setHttpResponseCode(405)->setHeader('Allow', 'POST', true);
        }
        if (!$this->config->isWebhookEnabled()) {
            return $this->rawFactory->create()->setHttpResponseCode(404);
        }

        // The vendor controller updates a database timestamp before checking the signature.
        // Authenticate the original bytes first so unauthenticated requests never reach that write.
        $signature = $this->request->getHeader('X-Razorpay-Signature');
        $secret = (string) $this->config->getWebhookSecret();
        $body = (string) $this->request->getContent();
        if ($secret === '' || $body === '' || !is_string($signature)
            || preg_match('/\A[a-f0-9]{64}\z/', $signature) !== 1
            || !hash_equals(hash_hmac('sha256', $body, $secret), $signature)
        ) {
            return $this->rawFactory->create()->setHttpResponseCode(400);
        }

        return $proceed();
    }
}
