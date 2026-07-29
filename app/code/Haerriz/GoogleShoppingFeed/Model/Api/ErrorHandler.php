<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Psr\Log\LoggerInterface;
use Google\ApiCore\ApiException;

class ErrorHandler
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle API exception and decide whether to retry with backoff or fail permanently
     *
     * @param \Exception $exception
     * @param int $attempt Current attempt number
     * @return bool True if error is transient and we should retry, false otherwise
     */
    public function handleException(\Exception $exception, $attempt = 1)
    {
        $code = $exception->getCode();
        $message = $exception->getMessage();

        if ($exception instanceof ApiException) {
            $status = $exception->getStatus();
            $this->logger->warning(sprintf("Google API Error: Status %s. Code: %s. Message: %s", $status, $code, $message));

            // Transient error status checks (5xx or 429 Quota Exceeded)
            if ($code === 429 || ($code >= 500 && $code <= 599)) {
                $this->executeBackoffWithJitter($attempt);
                return true;
            }
        } else {
            $this->logger->error("System Sync Error: " . $message);
        }

        return false; // Permanent error
    }

    /**
     * Execute backoff with jitter
     *
     * @param int $attempt
     * @return void
     */
    protected function executeBackoffWithJitter($attempt)
    {
        $base = pow(2, $attempt);
        $jitter = rand(0, 1000) / 1000; // float between 0 and 1
        $delaySeconds = $base + $jitter;
        
        $this->logger->info(sprintf("Transient error. Retrying in %s seconds...", round($delaySeconds, 2)));
        usleep((int)($delaySeconds * 1000000));
    }
}
