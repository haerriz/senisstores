<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\JobLoggerInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedLogFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedLogRepository;
use Psr\Log\LoggerInterface;

class FeedLogHandler implements JobLoggerInterface
{
    private $logFactory;
    private $logRepository;
    private $logger;

    // In-memory buffer: flush on every N messages to avoid DB overload
    private array $buffer   = [];
    private int   $flushAt  = 25;

    public function __construct(
        FeedLogFactory $logFactory,
        FeedLogRepository $logRepository,
        LoggerInterface $logger
    ) {
        $this->logFactory    = $logFactory;
        $this->logRepository = $logRepository;
        $this->logger        = $logger;
    }

    /**
     * Implements JobLoggerInterface::log()
     * Buffers messages and flushes to DB.
     */
    public function log($job, string $level, string $message, array $context = []): void
    {
        // Always log to system logger too
        $prefix = $job ? "[Job#{$job->getId()}] " : "[NoJob] ";
        match ($level) {
            'error'   => $this->logger->error($prefix . $message, $context),
            'warning' => $this->logger->warning($prefix . $message, $context),
            'debug'   => $this->logger->debug($prefix . $message, $context),
            default   => $this->logger->info($prefix . $message, $context),
        };

        // log() also implements the interface signature: log(int $jobId, ...)
        // We handle both a FeedJob object and a raw int
        $jobId = is_object($job) ? (int)$job->getId() : (int)$job;

        if ($jobId <= 0) {
            return; // No job to attach log to
        }

        try {
            $logEntry = $this->logFactory->create();
            $logEntry->setJobId($jobId);
            $logEntry->setLevel($level);
            $logEntry->setMessage($message);
            $logEntry->setContext(json_encode($context) ?: '{}');
            $logEntry->setCreatedAt(date('Y-m-d H:i:s'));

            $this->buffer[] = $logEntry;

            if (count($this->buffer) >= $this->flushAt) {
                $this->flush();
            }
        } catch (\Exception $e) {
            $this->logger->debug("FeedLogHandler: Could not buffer log: " . $e->getMessage());
        }
    }

    /**
     * Flush all buffered log entries to DB.
     */
    public function flush(): void
    {
        foreach ($this->buffer as $entry) {
            try {
                $this->logRepository->save($entry);
            } catch (\Exception $e) {
                $this->logger->debug("FeedLogHandler: flush save failed: " . $e->getMessage());
            }
        }
        $this->buffer = [];
    }

    public function __destruct()
    {
        if (!empty($this->buffer)) {
            $this->flush();
        }
    }
}
