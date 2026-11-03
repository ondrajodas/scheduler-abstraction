<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Scheduler\ScheduledJob;
use App\Scheduler\SchedulerInterface;
use App\Tests\Support\CallbackJob;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Contract every SchedulerInterface implementation (Laravel, crunz, ...) must satisfy,
 * so switching the implementation does not change behaviour.
 */
abstract class SchedulerTestCase extends TestCase
{
    abstract protected function createScheduler(): SchedulerInterface;

    /**
     * Sets the current time seen by the scheduler implementation.
     */
    abstract protected function setNow(string $time): void;

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function cronCases(): iterable
    {
        yield 'every minute' => ['* * * * *', '2026-11-03 10:15:42 UTC', true];
        yield 'every 15 minutes, on slot' => ['*/15 * * * *', '2026-11-03 10:15:00 UTC', true];
        yield 'every 15 minutes, off slot' => ['*/15 * * * *', '2026-11-03 10:16:00 UTC', false];
        yield 'daily 03:00, on time' => ['0 3 * * *', '2026-11-03 03:00:59 UTC', true];
        yield 'daily 03:00, other time' => ['0 3 * * *', '2026-11-03 10:00:00 UTC', false];
        yield 'hourly alias' => ['@hourly', '2026-11-03 10:00:30 UTC', true];
        yield 'weekdays only, on Saturday' => ['0 9 * * 1-5', '2026-11-07 09:00:00 UTC', false];
    }

    #[DataProvider('cronCases')]
    public function testRunsJobOnlyWhenDue(string $cron, string $now, bool $expectedRun): void
    {
        $this->setNow($now);
        $job = new CallbackJob();
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('job', $cron, $job));

        $scheduler->runDue();

        self::assertSame($expectedRun ? 1 : 0, $job->runs);
    }

    public function testEvaluatesCronInJobTimezone(): void
    {
        $this->setNow('2026-11-03 10:15:00 UTC');
        $prague = new CallbackJob();
        $utc = new CallbackJob();
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('prague', '15 11 * * *', $prague, timezone: new DateTimeZone('Europe/Prague')));
        $scheduler->add(new ScheduledJob('utc', '15 11 * * *', $utc, timezone: new DateTimeZone('UTC')));

        $scheduler->runDue();

        self::assertSame([1, 0], [$prague->runs, $utc->runs]);
    }

    public function testFailingJobDoesNotStopOtherJobs(): void
    {
        $this->setNow('2026-11-03 10:15:00 UTC');
        $failing = new CallbackJob(fn () => throw new RuntimeException('boom'));
        $next = new CallbackJob();
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('failing', '* * * * *', $failing));
        $scheduler->add(new ScheduledJob('next', '* * * * *', $next));

        $scheduler->runDue();

        self::assertSame([1, 1], [$failing->runs, $next->runs]);
    }

    public function testLongRunningJobDoesNotMakeNextDueJobMissItsMinute(): void
    {
        $this->setNow('2026-11-03 10:15:00 UTC');
        $long = new CallbackJob(fn () => $this->setNow('2026-11-03 10:18:00 UTC'));
        $next = new CallbackJob();
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('long', '* * * * *', $long));
        $scheduler->add(new ScheduledJob('next', '15 10 * * *', $next));

        $scheduler->runDue();

        self::assertSame(1, $next->runs);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCronCases(): iterable
    {
        yield 'out of range' => ['99 * * * *'];
        yield 'wrong field count' => ['* * *'];
        yield 'impossible date' => ['0 0 30 2 *'];
    }

    #[DataProvider('invalidCronCases')]
    public function testRejectsInvalidCronExpression(string $cron): void
    {
        $scheduler = $this->createScheduler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid cron expression "%s"', $cron));
        $scheduler->add(new ScheduledJob('job', $cron, new CallbackJob()));
    }

    public function testRejectsDuplicateName(): void
    {
        $scheduler = $this->createScheduler();
        $scheduler->add(new ScheduledJob('job', '* * * * *', new CallbackJob()));

        $this->expectException(InvalidArgumentException::class);
        $scheduler->add(new ScheduledJob('job', '0 * * * *', new CallbackJob()));
    }
}
