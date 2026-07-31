<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface JobLoggerInterface
{
    public function log(int $jobId, string $level, string $message, array $context = []);
}
