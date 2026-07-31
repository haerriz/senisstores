<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface ArtifactManagerInterface
{
    public function createArtifact(int $jobId, string $filename, string $content): \Haerriz\GoogleShoppingFeed\Api\Data\ArtifactInterface;
}
