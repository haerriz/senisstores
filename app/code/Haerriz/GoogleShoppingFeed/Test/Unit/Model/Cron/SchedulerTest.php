<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Cron;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Cron\Scheduler;

class SchedulerTest extends TestCase
{
    protected $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new Scheduler();
    }

    public function testCalculateNextRunHourly()
    {
        $fromTime = '2026-07-29 10:15:30';
        $nextRun = $this->scheduler->calculateNextRun('hourly', null, 'UTC', $fromTime);
        $this->assertEquals('2026-07-29 11:15:00', $nextRun);
    }

    public function testCalculateNextRunDaily()
    {
        $fromTime = '2026-07-29 10:15:30';
        $nextRun = $this->scheduler->calculateNextRun('daily', null, 'UTC', $fromTime);
        $this->assertEquals('2026-07-30 02:00:00', $nextRun);
    }

    public function testCalculateNextRunCustomCronExpression()
    {
        $fromTime = '2026-07-29 10:15:30';
        // Run every minute
        $nextRun = $this->scheduler->calculateNextRun('custom', '* * * * *', 'UTC', $fromTime);
        $this->assertEquals('2026-07-29 10:16:00', $nextRun);
    }

    public function testCalculateNextRunTimezoneAwareness()
    {
        $fromTime = '2026-07-29 10:15:30'; // UTC
        // Daily runs at 2 AM local timezone time.
        // EST is UTC-5 (or UTC-4 in daylight savings).
        // Let's use UTC+2 (Europe/Paris or similar, e.g. Africa/Cairo, which doesn't change DST or we compute relative to 2 AM local).
        $nextRun = $this->scheduler->calculateNextRun('daily', null, 'Africa/Cairo', $fromTime);
        // local time is 12:15:30 (UTC+2).
        // Next day local time is 2026-07-30 02:00:00.
        // Conversion back to UTC: 2026-07-30 00:00:00.
        $this->assertEquals('2026-07-30 00:00:00', $nextRun);
    }
}
