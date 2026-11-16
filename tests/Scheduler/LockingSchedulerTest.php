<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Mutex\InMemoryMutex;
use App\Scheduler\Laravel\LaravelScheduler;
use App\Scheduler\LockHeartbeat;
use App\Scheduler\LockLostException;
use App\Scheduler\LockingScheduler;
use App\Scheduler\ScheduledJob;
use App\Tests\Support\CallbackJob;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingLogger;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class LockingSchedulerTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryMutex $mutex;
    private RecordingLogger $logger;
    private LockingScheduler $scheduler;

    protected function setUp(): void
    {
        $this->clock = FixedClock::at('2026-11-03 10:15:00 UTC');
        $this->mutex = new InMemoryMutex($this->clock);
        $this->logger = new RecordingLogger();
        $laravel = new LaravelScheduler($this->logger);
        $this->scheduler = new LockingScheduler($laravel, $this->mutex, new NullLogger(), $this->clock);
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

    public function testHeartbeatKeepsLockWhileJobRunsLongerThanTtl(): void
    {
        $lockedDuringRun = null;
        $long = new CallbackJob(function (LockHeartbeat $heartbeat) use (&$lockedDuringRun): void {
            $this->moveTo('2026-11-03 10:16:00 UTC');
            $heartbeat->beat();
            $this->moveTo('2026-11-03 10:17:30 UTC');
            $lockedDuringRun = $this->mutex->acquire('scheduler:sync', 60) === null;
        });
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $long, lockTtlSeconds: 120));

        $this->scheduler->runDue();

        self::assertTrue($lockedDuringRun);
    }

    public function testJobFailsWhenItsLockWasLost(): void
    {
        $long = new CallbackJob(function (LockHeartbeat $heartbeat): void {
            $this->moveTo('2026-11-03 10:18:00 UTC');
            $this->mutex->acquire('scheduler:sync', 600);
            $heartbeat->beat();
        });
        $this->scheduler->add(new ScheduledJob('sync', '* * * * *', $long, lockTtlSeconds: 120));

        $this->scheduler->runDue();

        $exception = $this->logger->records[0]['context']['exception'] ?? null;
        self::assertInstanceOf(LockLostException::class, $exception);
        self::assertNull($this->mutex->acquire('scheduler:sync', 60), 'the new owner keeps its lock');
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
