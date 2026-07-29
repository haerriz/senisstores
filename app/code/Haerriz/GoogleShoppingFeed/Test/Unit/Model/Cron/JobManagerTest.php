<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Cron;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Cron\JobManager;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob\CollectionFactory as JobCollectionFactory;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedJob\Collection as JobCollection;
use Haerriz\GoogleShoppingFeed\Api\FeedJobRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedJob;
use Haerriz\GoogleShoppingFeed\Model\FeedGenerator;
use Magento\Framework\Stdlib\DateTime\DateTime;

class JobManagerTest extends TestCase
{
    protected $jobManager;
    protected $collectionFactoryMock;
    protected $repositoryMock;
    protected $generatorMock;
    protected $dateTimeMock;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(JobCollectionFactory::class);
        $this->repositoryMock = $this->createMock(FeedJobRepositoryInterface::class);
        $this->generatorMock = $this->createMock(FeedGenerator::class);
        $this->dateTimeMock = $this->createMock(DateTime::class);

        $this->jobManager = new JobManager(
            $this->collectionFactoryMock,
            $this->repositoryMock,
            $this->generatorMock,
            $this->dateTimeMock
        );
    }

    public function testCancelJobUpdatesStatus()
    {
        $jobMock = $this->createMock(FeedJob::class);
        $jobMock->expects($this->any())
            ->method('getStatus')
            ->willReturn('running');

        $jobMock->expects($this->once())
            ->method('setStatus')
            ->with('cancelled');

        $this->repositoryMock->expects($this->once())
            ->method('getById')
            ->with(123)
            ->willReturn($jobMock);

        $this->repositoryMock->expects($this->once())
            ->method('save')
            ->with($jobMock);

        $this->dateTimeMock->expects($this->once())
            ->method('gmtDate')
            ->willReturn('2026-07-29 12:00:00');

        $this->assertTrue($this->jobManager->cancelJob(123));
    }
}
