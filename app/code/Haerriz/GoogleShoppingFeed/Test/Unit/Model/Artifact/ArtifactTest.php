<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Artifact;

use Haerriz\GoogleShoppingFeed\Api\Data\ArtifactInterface;
use Haerriz\GoogleShoppingFeed\Model\Artifact\Artifact;
use PHPUnit\Framework\TestCase;

class ArtifactTest extends TestCase
{
    public function testPathContractUsesArtifactFilePath(): void
    {
        $artifact = new Artifact('feed.xml', '/var/feeds/feed.xml', 321);

        self::assertInstanceOf(ArtifactInterface::class, $artifact);
        self::assertSame('/var/feeds/feed.xml', $artifact->getPath());
        self::assertSame('/var/feeds/feed.xml', $artifact->getFilePath());
        self::assertSame('feed.xml', $artifact->getFilename());
        self::assertSame(321, $artifact->getSize());
    }
}
