<?php
namespace Haerriz\GoogleShoppingFeed\Model\Delivery;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class WebhookNotifier
{
    public const XML_PATH_WEBHOOK_URL = 'haerriz_googleshoppingfeed/alerts/delivery_webhook_url';

    private ScopeConfigInterface $scopeConfig;
    private Curl $curl;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $exportResult
     */
    public function notify(FeedProfileInterface $profile, array $exportResult = []): void
    {
        $url = trim((string)($profile->getData('webhook_url') ?: ''));
        if ($url === '') {
            $url = trim((string)$this->scopeConfig->getValue(
                self::XML_PATH_WEBHOOK_URL,
                ScopeInterface::SCOPE_STORE,
                $profile->getStoreId()
            ));
        }
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return;
        }

        $payload = [
            'profile_id' => (int)$profile->getId(),
            'filename' => (string)$profile->getFilename(),
            'exported' => (int)($exportResult['exported'] ?? 0),
            'checksum' => (string)($exportResult['checksum'] ?? ''),
        ];

        try {
            $this->curl->setHeaders(['Content-Type' => 'application/json']);
            $this->curl->post($url, json_encode($payload));
            $this->logger->info(sprintf(
                'WebhookNotifier: posted delivery notice for profile #%d to %s',
                (int)$profile->getId(),
                $url
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('WebhookNotifier failed: ' . $e->getMessage());
        }
    }
}
