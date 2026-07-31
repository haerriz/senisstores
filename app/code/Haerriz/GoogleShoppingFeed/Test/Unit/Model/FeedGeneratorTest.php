<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\ProductTypeResolverInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedExporter;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Haerriz\GoogleShoppingFeed\Model\FeedJob;
use Haerriz\GoogleShoppingFeed\Model\FeedJobFactory;
use Haerriz\GoogleShoppingFeed\Model\Logger\Sanitizer;
use Haerriz\GoogleShoppingFeed\Model\Modifier\Pool as ModifierPool;
use Haerriz\GoogleShoppingFeed\Model\ProfileValidator;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob as JobResource;
use Haerriz\GoogleShoppingFeed\Model\RuleFactory;
use Haerriz\GoogleShoppingFeed\Model\Storage\AdapterPool;
use Haerriz\GoogleShoppingFeed\Model\Url\UtmBuilder;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FeedGeneratorTest extends TestCase
{
    public function testInvalidProfileNeverPublishesPartialArtifact()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $directory = $this->createMock(WriteInterface::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);
        $directory->expects($this->never())->method('renameFile');
        $directory->method('isExist')->willReturn(false);

        $profile = $this->createMock(FeedProfileInterface::class);
        $profile->method('getId')->willReturn(7);
        $profile->method('getFeedType')->willReturn('xml');
        $profile->method('getFilename')->willReturn('feed.xml');

        $job = $this->getMockBuilder(FeedJob::class)->disableOriginalConstructor()->getMock();
        $jobFactory = $this->createMock(FeedJobFactory::class);
        $jobFactory->method('create')->willReturn($job);
        $jobResource = $this->createMock(JobResource::class);
        $jobResource->expects($this->atLeast(2))->method('save')->with($job);

        $validator = $this->createMock(ProfileValidator::class);
        $validator->method('assertValid')->willThrowException(new \InvalidArgumentException('invalid'));
        $exporter = $this->createMock(FeedExporter::class);
        $exporter->expects($this->never())->method('export');

        $generator = new FeedGenerator(
            $this->createMock(ProductProviderInterface::class),
            $this->createMock(ProductTypeResolverInterface::class),
            $filesystem,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ModifierPool::class),
            $this->createMock(AdapterPool::class),
            $this->createMock(RuleFactory::class),
            $jobFactory,
            $jobResource,
            $this->createMock(UtmBuilder::class),
            new Sanitizer(),
            $exporter,
            $validator
        );

        $this->assertFalse($generator->generate($profile, 'manual'));
    }
}
