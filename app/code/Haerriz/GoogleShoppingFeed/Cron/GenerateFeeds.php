<?php
namespace Haerriz\GoogleShoppingFeed\Cron;

use Haerriz\GoogleShoppingFeed\Model\Cron\Dispatcher;
use Psr\Log\LoggerInterface;

class GenerateFeeds
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Dispatcher $dispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        Dispatcher $dispatcher,
        LoggerInterface $logger
    ) {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    /**
     * Run dynamic schedulers dispatcher
     *
     * @return $this
     */
    public function execute()
    {
        $this->logger->info("Starting dynamic scheduling Google Shopping Feed dispatch loops.");
        $this->dispatcher->dispatch();
        $this->logger->info("Finished dynamic scheduling Google Shopping Feed dispatch loops.");
        return $this;
    }
}
