<?php
namespace Haerriz\SmtpRouting\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Psr\Log\LoggerInterface;

class TransportPlugin
{
    protected $deploymentConfig;
    protected $logger;

    public function __construct(
        DeploymentConfig $deploymentConfig,
        LoggerInterface $logger
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->logger = $logger;
    }

    public function aroundSendMessage(\Magento\Email\Model\Transport $subject, callable $proceed)
    {
        try {
            // Get the raw message string to rebuild Laminas message
            // Wait, in Magento 2.4.7, $subject->getMessage() returns \Magento\Framework\Mail\MessageInterface
            $message = $subject->getMessage();
            $laminasMessage = \Laminas\Mail\Message::fromString($message->getRawMessage())->setEncoding('utf-8');
            
            $fromList = $laminasMessage->getFrom();
            $fromList->rewind();
            $fromAddress = $fromList->current();
            $fromEmail = $fromAddress ? $fromAddress->getEmail() : null;

            if ($fromEmail) {
                // Determine credentials based on From address
                // Retrieve custom smtp_mapping from env.php
                $mapping = $this->deploymentConfig->get('smtp_mapping', []);
                
                $username = null;
                $password = null;

                if (isset($mapping[$fromEmail])) {
                    $username = $fromEmail;
                    $password = $mapping[$fromEmail];
                } else {
                    // Fallback to primary if not found in mapping
                    $username = 'admin@senisstores.com';
                    $password = 'Whatsapp@2026';
                }

                $options = new SmtpOptions([
                    'name'              => 'localhost',
                    'host'              => 'smtp.hostinger.com',
                    'port'              => 465,
                    'connection_class'  => 'login',
                    'connection_config' => [
                        'username' => $username,
                        'password' => $password,
                        'ssl'      => 'ssl',
                    ],
                ]);

                $transport = new Smtp();
                $transport->setOptions($options);
                $transport->send($laminasMessage);
                
                // Bypass core sendMessage since we already sent it
                return;
            }
        } catch (\Exception $e) {
            $this->logger->critical('Haerriz_SmtpRouting Error: ' . $e->getMessage());
        }

        // Fallback to core logic if something fails
        return $proceed();
    }
}
