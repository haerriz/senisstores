<?php
namespace Haerriz\GoogleShoppingFeed\Model\Writer;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\WriterInterface;

class JsonLines implements WriterInterface
{
    public function start($stream, FeedProfileInterface $profile, array $fields)
    {
    }

    public function writeRow($stream, FeedProfileInterface $profile, array $row)
    {
        $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode feed row as JSON.');
        }
        $stream->write($json . "\n");
    }

    public function finish($stream, FeedProfileInterface $profile)
    {
    }
}
