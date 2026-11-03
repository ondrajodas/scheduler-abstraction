<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Mutex\InMemoryMutex;
use App\Scheduler\Laravel\LaravelScheduler;
use App\Scheduler\LockingScheduler;
use App\Scheduler\ScheduledJob;
use App\Tests\Support\CallbackJob;
use App\Tests\Support\FixedClock;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class LockingSchedulerTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryMutex $mutex;
    private LockingScheduler $scheduler;

    protected function setUp(): void
    {
        $this->clock = FixedClock::at('2026-11-03 10:15:00 UTC');
        $this->mutex = new InMemoryMutex($this->clock);
        $this->scheduler = new LockingScheduler(new LaravelScheduler(new NullLogger()), $this->mutex, new NullLogger());
        $this->moveTo('2026-11-03 10:15:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testSkipsRunWhilePreviousRunHoldsTheLock(): void
    {
        $job = new CallbackJob();
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $job));
        $this->mutex->acquire('scheduler:sync', 600);

        $this->scheduler->runDue();

        self::assertSame(0, $job->runs);
    }

    public function testReleasesLockAfterRun(): void
    {
        $job = new CallbackJob();
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $job));

        $this->scheduler->runDue();
        $this->scheduler->runDue();

        self::assertSame(2, $job->runs);
    }

    public function testReleasesLockWhenJobFails(): void
    {
        $failing = new CallbackJob(fn () => throw new RuntimeException('boom'));
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $failing));

        $this->scheduler->runDue();

        self::assertNotNull($this->mutex->acquire('scheduler:sync', 60));
    }

    public function testRunsAgainAfterStaleLockExpires(): void
    {
        $job = new CallbackJob();
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $job, lockTtlSeconds: 120));
        $this->mutex->acquire('scheduler:sync', 120);

        $this->moveTo('2026-11-03 10:18:00 UTC');
        $this->scheduler->runDue();

        self::assertSame(1, $job->runs);
    }

    public function testDoesNotLockWhenOverlappingIsAllowed(): void
    {
        $job = new CallbackJob();
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $job, withoutOverlapping: false));
        $this->mutex->acquire('scheduler:sync', 600);

        $this->scheduler->runDue();

        self::assertSame(1, $job->runs);
    }

    /**
     * Keeps the mutex clock and Laravel's clock (Carbon) in sync.
     */
    private function moveTo(string $time): void
    {
        $this->clock->moveTo($time);
        Carbon::setTestNow(Carbon::parse($time));
    }
}
