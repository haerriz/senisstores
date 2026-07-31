<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Model\Generation\Orchestrator;

class FeedGenerator
{
    private $exporter;
    private $orchestrator;

    public function __construct(
        FeedExporter $exporter,
        Orchestrator $orchestrator
    ) {
        $this->exporter     = $exporter;
        $this->orchestrator = $orchestrator;
    }

    public function generate(FeedProfileInterface $profile, string $triggerSource = 'manual'): array
    {
        // Route through Orchestrator for lock management, snapshot, and failure classification
        return $this->orchestrator->run($profile, $triggerSource);
    }
}
