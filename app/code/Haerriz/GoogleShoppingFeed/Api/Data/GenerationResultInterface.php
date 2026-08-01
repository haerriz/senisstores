<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface GenerationResultInterface
{
    public function isSuccess(): bool;

    public function getJobId(): int;

    public function getExportedCount(): int;

    public function getErrorMessage(): ?string;
}
