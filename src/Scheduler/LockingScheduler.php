<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Mutex\MutexInterface;
use Psr\Log\LoggerInterface;

/**
 * Adds our own overlap locking on top of any scheduler implementation,
 * so the implementations never use their own (unreliable) locking.
 */
final readonly class LockingScheduler implements SchedulerInterface
{
    private const LOCK_PREFIX = 'scheduler:';

    public function __construct(
        private SchedulerInterface $scheduler,
        private MutexInterface $mutex,
        private LoggerInterface $logger,
    ) {
    }

    public function add(ScheduledJob $job): void
    {
        $this->scheduler->add($job->withoutOverlapping ? $job->withJob($this->lock($job)) : $job);
    }

    public function runDue(): void
    {
        $this->scheduler->runDue();
    }

    private function lock(ScheduledJob $job): LockedJob
    {
        return new LockedJob($job->job, $this->mutex, self::LOCK_PREFIX . $job->name, $job->lockTtlSeconds, $this->logger);
    }
}
