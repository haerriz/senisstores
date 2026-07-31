<?php
namespace Haerriz\GoogleShoppingFeed\Model\Writer;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\WriterInterface;
use Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader;

class Delimited implements WriterInterface
{
    private $configReader;
    private $fixedDelimiter;

    public function __construct(ProfileConfigReader $configReader, $fixedDelimiter = null)
    {
        $this->configReader = $configReader;
        $this->fixedDelimiter = $fixedDelimiter;
    }

    public function start($stream, FeedProfileInterface $profile, array $fields)
    {
        $this->write($stream, $profile, $fields);
    }

    public function writeRow($stream, FeedProfileInterface $profile, array $row)
    {
        $this->write($stream, $profile, array_values($row));
    }

    public function finish($stream, FeedProfileInterface $profile)
    {
    }

    private function write($stream, FeedProfileInterface $profile, array $values)
    {
        $delimiter = $this->fixedDelimiter ?: (string)$this->configReader->get($profile, 'delimiter', ',');
        $enclosure = (string)$this->configReader->get($profile, 'enclosure', '"');
        if (strlen($delimiter) !== 1 || strlen($enclosure) !== 1) {
            throw new \InvalidArgumentException('Delimiter and enclosure must each be exactly one byte.');
        }
        $memory = fopen('php://temp', 'w+');
        if ($memory === false) {
            throw new \RuntimeException('Unable to allocate a bounded row buffer.');
        }
        try {
            fputcsv($memory, $values, $delimiter, $enclosure);
            rewind($memory);
            $line = rtrim((string)stream_get_contents($memory), "\r\n");
        } finally {
            fclose($memory);
        }
        $lineEnding = (string)$this->configReader->get($profile, 'line_ending', 'LF');
        $line .= $lineEnding === 'CRLF' ? "\r\n" : "\n";
        $encoding = (string)$this->configReader->get($profile, 'encoding', 'UTF-8');
        if (strcasecmp($encoding, 'UTF-8') !== 0) {
            $line = mb_convert_encoding($line, $encoding, 'UTF-8');
        }
        $stream->write($line);
    }
}
