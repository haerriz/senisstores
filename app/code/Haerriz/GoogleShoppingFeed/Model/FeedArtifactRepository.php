<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\FeedArtifactRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedArtifactInterface;

class FeedArtifactRepository implements FeedArtifactRepositoryInterface
{
    public function save(FeedArtifactInterface $artifact)
    {
        return $artifact;
    }

    public function getById(int $id)
    {
        return null;
    }
}
