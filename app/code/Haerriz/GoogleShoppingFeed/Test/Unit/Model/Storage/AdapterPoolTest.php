<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Storage\AdapterPool;
use Haerriz\GoogleShoppingFeed\Model\Storage\Ftp;
use Haerriz\GoogleShoppingFeed\Model\Storage\Local;
use Haerriz\GoogleShoppingFeed\Model\Storage\Sftp;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Psr\Log\LoggerInterface;

class AdapterPoolTest extends TestCase
{
    public function testGetResolvesAdapter()
    {
        $local = $this->createMock(Local::class);

        $pool = new AdapterPool(
            $local,
            $this->createMock(Ftp::class),
            $this->createMock(Sftp::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->assertSame($local, $pool->get('local'));
    }

    public function testDeliverRetriesFtpUpload()
    {
        $profile = $this->createMock(FeedProfileInterface::class);
        $profile->method('getDeliveryType')->willReturn('ftp');
        $profile->method('getId')->willReturn(7);

        $attempts = 0;
        $ftp = $this->createMock(Ftp::class);
        $ftp->expects($this->exactly(2))
            ->method('upload')
            ->with($profile, 'feeds/google.xml')
            ->willReturnCallback(function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1) {
                    throw new \RuntimeException('temporary outage');
                }
                return true;
            });

        $pool = new AdapterPool(
            $this->createMock(Local::class),
            $ftp,
            $this->createMock(Sftp::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->assertTrue($pool->deliver($profile, 'feeds/google.xml'));
    }
}
