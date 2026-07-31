<?php
namespace Haerriz\GoogleShoppingFeed\Api;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

interface WriterInterface
{
    public function start($stream, FeedProfileInterface $profile, array $fields);

    public function writeRow($stream, FeedProfileInterface $profile, array $row);

    public function finish($stream, FeedProfileInterface $profile);
}
