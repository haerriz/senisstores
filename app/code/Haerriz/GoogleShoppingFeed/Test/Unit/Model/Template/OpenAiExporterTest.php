<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Template;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Template\OpenAiExporter;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class OpenAiExporterTest extends TestCase
{
    protected $exporter;
    protected $filesystemMock;
    protected $directoryMock;

    protected function setUp(): void
    {
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->directoryMock = $this->createMock(WriteInterface::class);

        $this->filesystemMock->expects($this->any())
            ->method('getDirectoryWrite')
            ->willReturn($this->directoryMock);

        $this->exporter = new OpenAiExporter($this->filesystemMock);
    }

    public function testExportReturnsFalseOnEmptyFailure()
    {
        $this->directoryMock->expects($this->once())
            ->method('getAbsolutePath')
            ->willReturn('/invalid/path/file.gz');

        $result = $this->exporter->export([], 'file.gz', []);
        // Should catch connection exception or empty handler gracefully
        $this->assertFalse($result);
    }
}
