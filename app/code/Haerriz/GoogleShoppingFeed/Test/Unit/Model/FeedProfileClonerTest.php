<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterfaceFactory;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Haerriz\GoogleShoppingFeed\Model\FeedProfileCloner;
use PHPUnit\Framework\TestCase;

class FeedProfileClonerTest extends TestCase
{
    public function testDuplicateRemovesSecretsAndRuntimeState()
    {
        $source = $this->createMock(FeedProfile::class);
        $copy = $this->createMock(FeedProfile::class);
        $factory = $this->createMock(FeedProfileInterfaceFactory::class);
        $repository = $this->createMock(FeedProfileRepositoryInterface::class);

        $source->method('getData')->willReturn([
            'profile_id' => 7,
            'name' => 'Primary',
            'delivery_password' => 'encrypted',
            'next_run_at' => '2026-07-30 10:00:00',
            'is_locked' => 1,
            'retry_count' => 3,
            'consecutive_failures' => 2,
            'locale' => 'en_IN',
        ]);
        $source->method('getName')->willReturn('Primary');
        $factory->method('create')->willReturn($copy);
        $repository->expects($this->once())->method('save')->with($copy)->willReturn($copy);
        $copy->expects($this->once())->method('setData')->with($this->callback(function ($data) {
            return ($data['locale'] ?? null) === 'en_IN'
                && !isset($data['profile_id'])
                && !isset($data['delivery_password'])
                && ($data['next_run_at'] ?? 'x') === null
                && (int)($data['is_locked'] ?? 1) === 0
                && (int)($data['retry_count'] ?? 1) === 0
                && (int)($data['status'] ?? 1) === 0;
        }))->willReturnSelf();

        $cloner = new FeedProfileCloner($factory, $repository);

        $this->assertSame($copy, $cloner->duplicate($source));
    }
}
