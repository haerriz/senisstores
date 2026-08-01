<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Generation;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedExporter;
use Haerriz\GoogleShoppingFeed\Model\FeedJob;
use Haerriz\GoogleShoppingFeed\Model\FeedJobFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedJobRepository;
use Haerriz\GoogleShoppingFeed\Model\FeedLogHandler;
use Haerriz\GoogleShoppingFeed\Model\Generation\FailureClassifier;
use Haerriz\GoogleShoppingFeed\Model\Generation\Orchestrator;
use Haerriz\GoogleShoppingFeed\Model\Generation\ProfileLock;
use Haerriz\GoogleShoppingFeed\Model\Generation\ProfileSnapshot;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrchestratorPipelineTest extends TestCase
{
    public function testRunLocksSnapshotsExportsAndReleasesOnSuccess(): void
    {
        $profile = $this->profile();
        $job = $this->job(99);

        $lock = $this->createMock(ProfileLock::class);
        $lock->expects($this->once())->method('acquire')->with(7)->willReturn(true);
        $lock->expects($this->once())->method('release')->with(7);

        $snapshot = $this->createMock(ProfileSnapshot::class);
        $snapshot->expects($this->once())->method('take')->with($profile)->willReturn(['profile_id' => 7]);

        $exporter = $this->createMock(FeedExporter::class);
        $exporter->expects($this->once())
            ->method('export')
            ->with($profile, 'feeds/google.xml', $job)
            ->willReturn(['exported' => 3, 'fileSize' => 123]);

        $result = $this->orchestrator($exporter, $lock, $snapshot, $job)->run($profile, 'manual');

        $this->assertSame(99, $result['job_id']);
        $this->assertSame(3, $result['exported']);
    }

    public function testRunSkipsWhenProfileLockIsHeld(): void
    {
        $profile = $this->profile();
        $lock = $this->createMock(ProfileLock::class);
        $lock->expects($this->once())->method('acquire')->with(7)->willReturn(false);
        $lock->expects($this->never())->method('release');

        $exporter = $this->createMock(FeedExporter::class);
        $exporter->expects($this->never())->method('export');

        $snapshot = $this->createMock(ProfileSnapshot::class);
        $snapshot->expects($this->never())->method('take');

        $result = $this->orchestrator($exporter, $lock, $snapshot, $this->job(99))->run($profile);

        $this->assertTrue($result['skipped']);
        $this->assertSame('locked', $result['reason']);
    }

    public function testRunMarksJobErrorAndReleasesOnDeliveryFailure(): void
    {
        $profile = $this->profile();
        $job = $this->job(99);
        $failureMessageWasRecorded = false;
        $job->method('setData')->willReturnCallback(function ($key, $value) use (&$failureMessageWasRecorded, $job) {
            if ($key === 'failure_message' && $value === 'Delivery failed') {
                $failureMessageWasRecorded = true;
            }
            return $job;
        });

        $lock = $this->createMock(ProfileLock::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release')->with(7);

        $snapshot = $this->createMock(ProfileSnapshot::class);
        $snapshot->method('take')->willReturn(['profile_id' => 7]);

        $exporter = $this->createMock(FeedExporter::class);
        $exporter->method('export')->willThrowException(new \RuntimeException('Delivery failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Delivery failed');

        try {
            $this->orchestrator($exporter, $lock, $snapshot, $job)->run($profile);
        } finally {
            $this->assertTrue($failureMessageWasRecorded);
        }
    }

    private function orchestrator(
        FeedExporter $exporter,
        ProfileLock $lock,
        ProfileSnapshot $snapshot,
        FeedJob $job
    ): Orchestrator {
        $jobFactory = $this->createMock(FeedJobFactory::class);
        $jobFactory->method('create')->willReturn($job);

        $jobRepository = $this->createMock(FeedJobRepository::class);
        $jobRepository->method('save')->with($job);

        $classifier = $this->createMock(FailureClassifier::class);
        $classifier->method('classify')->willReturn('delivery');

        $feedLogHandler = $this->createMock(FeedLogHandler::class);
        $feedLogHandler->method('log');

        return new Orchestrator(
            $exporter,
            $jobFactory,
            $jobRepository,
            $classifier,
            $lock,
            $snapshot,
            $feedLogHandler,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function profile(): FeedProfileInterface
    {
        $profile = $this->createMock(FeedProfileInterface::class);
        $profile->method('getId')->willReturn(7);
        $profile->method('getFilename')->willReturn('pub/media/feeds/google.xml');
        $profile->method('getName')->willReturn('Google');
        return $profile;
    }

    private function job(int $id): FeedJob
    {
        $job = $this->getMockBuilder(FeedJob::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setProfileId', 'setTriggerSource', 'setStatus', 'setStartedAt', 'setData', 'getId', 'setFinishedAt'])
            ->getMock();
        $job->method('getId')->willReturn($id);
        return $job;
    }
}
