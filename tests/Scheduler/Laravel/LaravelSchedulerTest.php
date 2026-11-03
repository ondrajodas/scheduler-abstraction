<?php

declare(strict_types=1);

namespace App\Tests\Scheduler\Laravel;

use App\Scheduler\Laravel\LaravelScheduler;
use App\Scheduler\ScheduledJob;
use App\Scheduler\SchedulerInterface;
use App\Tests\Scheduler\SchedulerTestCase;
use App\Tests\Support\CallbackJob;
use App\Tests\Support\RecordingLogger;
use Carbon\Carbon;

final class LaravelSchedulerTest extends SchedulerTestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    protected function createScheduler(): SchedulerInterface
    {
        return new LaravelScheduler($this->logger);
    }

    protected function setNow(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time));
    }

    public function testNeverUsesLaravelMutex(): void
    {
        $this->setNow('2026-11-03 10:15:00 UTC');
        $job = new CallbackJob();
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('job', '* * * * *', $job, withoutOverlapping: true));

        $scheduler->runDue();
        $scheduler->runDue();

        self::assertSame(2, $job->runs);
        self::assertSame([], $this->logger->messagesAt('error'));
    }

    public function testLogsFailedJob(): void
    {
        $this->setNow('2026-11-03 10:15:00 UTC');
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('failing', '* * * * *', new CallbackJob(fn () => throw new \RuntimeException('boom'))));

        $scheduler->runDue();

        self::assertSame(['Scheduled job failed'], $this->logger->messagesAt('error'));
    }
}
