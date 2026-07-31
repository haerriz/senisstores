<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface FeedArtifactRepositoryInterface
{
    public function save(\Haerriz\GoogleShoppingFeed\Api\Data\FeedArtifactInterface $artifact);
    public function getById(int $id);
}
