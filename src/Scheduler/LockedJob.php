<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Mutex\MutexInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Skips the run when the previous one is still running (anywhere sharing the mutex backend).
 * The wrapped job gets a heartbeat bound to this lock; the incoming heartbeat is not used.
 */
final readonly class LockedJob implements JobInterface
{
    public function __construct(
        private JobInterface $job,
        private MutexInterface $mutex,
        private string $lockKey,
        private int $lockTtlSeconds,
        private LoggerInterface $logger,
        private ClockInterface $clock,
    ) {
    }

    public function run(LockHeartbeat $heartbeat): void
    {
        $lock = $this->mutex->acquire($this->lockKey, $this->lockTtlSeconds);
        if ($lock === null) {
            $this->logger->info('Scheduled job skipped, previous run still holds the lock', ['lock' => $this->lockKey]);

            return;
        }

        try {
            $this->job->run(new MutexHeartbeat($this->mutex, $lock, $this->lockTtlSeconds, $this->clock));
        } finally {
            $this->mutex->release($lock);
        }
    }
}
