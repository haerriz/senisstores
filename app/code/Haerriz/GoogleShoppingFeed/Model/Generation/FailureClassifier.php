<?php
namespace Haerriz\GoogleShoppingFeed\Model\Generation;

class FailureClassifier
{
    const CATEGORY_MAPPING    = 'mapping_error';
    const CATEGORY_IO         = 'io_error';
    const CATEGORY_NETWORK    = 'network_error';
    const CATEGORY_PERMISSION = 'permission_error';
    const CATEGORY_MEMORY     = 'memory_error';
    const CATEGORY_TIMEOUT    = 'timeout_error';
    const CATEGORY_UNKNOWN    = 'unknown_error';

    public function classify(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());
        $class   = get_class($e);

        if ($e instanceof \InvalidArgumentException && str_contains($message, 'mapping')) {
            return self::CATEGORY_MAPPING;
        }
        if (str_contains($message, 'permission') || str_contains($message, 'access denied')) {
            return self::CATEGORY_PERMISSION;
        }
        if (str_contains($message, 'memory') || $e instanceof \RuntimeException && str_contains($message, 'allocat')) {
            return self::CATEGORY_MEMORY;
        }
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return self::CATEGORY_TIMEOUT;
        }
        if (str_contains($message, 'network') || str_contains($message, 'connection refused')
            || str_contains($message, 'curl') || str_contains($message, 'socket')) {
            return self::CATEGORY_NETWORK;
        }
        if ($e instanceof \RuntimeException || str_contains($message, 'file') || str_contains($message, 'stream')) {
            return self::CATEGORY_IO;
        }
        return self::CATEGORY_UNKNOWN;
    }
}
