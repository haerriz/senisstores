<?php
namespace Haerriz\GoogleShoppingFeed\Model\Generation;

use Haerriz\GoogleShoppingFeed\Api\Data\GenerationResultInterface;

class GenerationResult implements GenerationResultInterface
{
    private bool $success;
    private int $jobId;
    private int $exportedCount;
    private ?string $errorMessage;

    public function __construct(bool $success, int $jobId, int $exportedCount = 0, ?string $errorMessage = null)
    {
        $this->success = $success;
        $this->jobId = $jobId;
        $this->exportedCount = $exportedCount;
        $this->errorMessage = $errorMessage;
    }

    public function isSuccess(): bool { return $this->success; }
    public function getJobId(): int { return $this->jobId; }
    public function getExportedCount(): int { return $this->exportedCount; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
}
