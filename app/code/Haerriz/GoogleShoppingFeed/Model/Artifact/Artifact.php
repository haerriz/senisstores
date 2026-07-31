<?php
namespace Haerriz\GoogleShoppingFeed\Model\Artifact;

use Haerriz\GoogleShoppingFeed\Api\Data\ArtifactInterface;

class Artifact implements ArtifactInterface
{
    private string $filename;
    private string $filePath;
    private int $size;

    public function __construct(string $filename, string $filePath, int $size)
    {
        $this->filename = $filename;
        $this->filePath = $filePath;
        $this->size = $size;
    }

    public function getFilename(): string { return $this->filename; }
    public function getFilePath(): string { return $this->filePath; }
    public function getSize(): int { return $this->size; }
}
