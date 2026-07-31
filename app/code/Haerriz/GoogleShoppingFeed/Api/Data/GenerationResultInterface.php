<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface GenerationResultInterface
{
    public function isSuccess(): bool;
    public function getExportedCount(): int;
}
