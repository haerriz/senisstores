<?php
namespace Haerriz\GoogleShoppingFeed\Model\Alert;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class FailureAlerter
{
    public const XML_PATH_ENABLED = 'haerriz_googleshoppingfeed/alerts/enabled';
    public const XML_PATH_THRESHOLD = 'haerriz_googleshoppingfeed/alerts/threshold';
    public const XML_PATH_EMAIL = 'haerriz_googleshoppingfeed/alerts/email';
    public const XML_PATH_SLACK_WEBHOOK = 'haerriz_googleshoppingfeed/alerts/slack_webhook_url';

    private ScopeConfigInterface $scopeConfig;
    private TransportBuilder $transportBuilder;
    private Curl $curl;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    public function maybeAlert(FeedProfileInterface $profile, string $errorMessage = ''): void
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return;
        }

        $threshold = max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_THRESHOLD, ScopeInterface::SCOPE_STORE));
        $failures = (int)$profile->getConsecutiveFailures();
        if ($failures < $threshold) {
            return;
        }

        $subject = sprintf(
            'Feed profile #%d "%s" failed %d times consecutively',
            (int)$profile->getId(),
            (string)$profile->getName(),
            $failures
        );
        $body = $subject . ($errorMessage !== '' ? "\n\nLast error:\n" . $errorMessage : '');

        $this->sendEmail($subject, $body, (int)$profile->getStoreId());
        $this->postWebhook($profile, $failures, $errorMessage);
    }

    private function sendEmail(string $subject, string $body, int $storeId): void
    {
        $email = trim((string)$this->scopeConfig->getValue(self::XML_PATH_EMAIL, ScopeInterface::SCOPE_STORE, $storeId));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $this->sendSimpleMail($email, $subject, $body);
        } catch (\Throwable $e) {
            try {
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier('haerriz_googleshoppingfeed_failure_alert')
                    ->setTemplateOptions([
                        'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                        'store' => $storeId ?: \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ])
                    ->setTemplateVars([
                        'subject' => $subject,
                        'message' => $body,
                    ])
                    ->setFromByScope('general', $storeId)
                    ->addTo($email)
                    ->getTransport();
                $transport->sendMessage();
            } catch (\Throwable $inner) {
                $this->logger->warning('FailureAlerter email failed: ' . $inner->getMessage());
            }
        }
    }

    private function sendSimpleMail(string $email, string $subject, string $body): void
    {
        if (!class_exists(\Laminas\Mail\Message::class) && !class_exists(\Zend\Mail\Message::class)) {
            // Last resort: PHP mail().
            @mail($email, $subject, $body);
            return;
        }

        if (class_exists(\Laminas\Mail\Message::class)) {
            $message = new \Laminas\Mail\Message();
            $message->setEncoding('UTF-8');
            $message->addTo($email);
            $message->setSubject($subject);
            $message->setBody($body);
            $transport = new \Laminas\Mail\Transport\Sendmail();
            $transport->send($message);
            return;
        }

        $message = new \Zend\Mail\Message();
        $message->setEncoding('UTF-8');
        $message->addTo($email);
        $message->setSubject($subject);
        $message->setBody($body);
        $transport = new \Zend\Mail\Transport\Sendmail();
        $transport->send($message);
    }

    private function postWebhook(FeedProfileInterface $profile, int $failures, string $errorMessage): void
    {
        $webhook = trim((string)$this->scopeConfig->getValue(self::XML_PATH_SLACK_WEBHOOK, ScopeInterface::SCOPE_STORE));
        if ($webhook === '' || !preg_match('#^https?://#i', $webhook)) {
            return;
        }

        try {
            $payload = [
                'text' => sprintf(
                    'Feed profile #%d "%s" hit %d consecutive failures. %s',
                    (int)$profile->getId(),
                    (string)$profile->getName(),
                    $failures,
                    $errorMessage
                ),
                'profile_id' => (int)$profile->getId(),
                'consecutive_failures' => $failures,
                'error' => $errorMessage,
            ];
            $this->curl->setHeaders(['Content-Type' => 'application/json']);
            $this->curl->post($webhook, json_encode($payload));
        } catch (\Throwable $e) {
            $this->logger->warning('FailureAlerter webhook failed: ' . $e->getMessage());
        }
    }
}
