<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\File\WriteInterface as FileWriteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Pool as ModifierPool;

class FeedGeneratorTest extends TestCase
{
    protected $productCollectionFactoryMock;
    protected $productCollectionMock;
    protected $filesystemMock;
    protected $directoryWriteMock;
    protected $fileStreamMock;
    protected $storeManagerMock;
    protected $storeMock;
    protected $modifierPoolMock;
    protected $profileMock;
    protected $generator;

    protected function setUp(): void
    {
        $this->productCollectionFactoryMock = $this->createMock(ProductCollectionFactory::class);
        $this->productCollectionMock = $this->createMock(ProductCollection::class);
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->directoryWriteMock = $this->createMock(WriteInterface::class);
        $this->fileStreamMock = $this->createMock(FileWriteInterface::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeMock = $this->createMock(StoreInterface::class);
        $this->modifierPoolMock = $this->createMock(ModifierPool::class);
        $this->profileMock = $this->createMock(FeedProfileInterface::class);

        $this->generator = new FeedGenerator(
            $this->productCollectionFactoryMock,
            $this->filesystemMock,
            $this->storeManagerMock,
            $this->modifierPoolMock
        );
    }

    public function testGenerateXml()
    {
        $this->profileMock->method('getStoreId')->willReturn(1);
        $this->profileMock->method('getFeedType')->willReturn('xml');
        $this->profileMock->method('getFilename')->willReturn('feed.xml');
        $this->profileMock->method('getName')->willReturn('My Feed');
        $this->profileMock->method('getAttributesMappingSerialized')->willReturn('[]');

        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getBaseUrl')->willReturn('http://test.com/');

        $this->filesystemMock->method('getDirectoryWrite')->willReturn($this->directoryWriteMock);
        $this->directoryWriteMock->method('openFile')->willReturn($this->fileStreamMock);

        $this->productCollectionFactoryMock->method('create')->willReturn($this->productCollectionMock);
        $this->productCollectionMock->method('count')->willReturnOnConsecutiveCalls(0);

        $this->fileStreamMock->expects($this->once())->method('lock');
        $this->fileStreamMock->expects($this->atLeastOnce())->method('write');
        $this->fileStreamMock->expects($this->once())->method('unlock');
        $this->fileStreamMock->expects($this->once())->method('close');

        $result = $this->generator->generate($this->profileMock);
        $this->assertTrue($result);
    }
}
