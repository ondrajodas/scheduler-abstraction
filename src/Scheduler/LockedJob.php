<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Mutex\MutexInterface;
use Psr\Log\LoggerInterface;

/**
 * Skips the run when the previous one is still running (anywhere sharing the mutex backend).
 */
final readonly class LockedJob implements JobInterface
{
    public function __construct(
        private JobInterface $job,
        private MutexInterface $mutex,
        private string $lockKey,
        private int $lockTtlSeconds,
        private LoggerInterface $logger,
    ) {
    }

    public function run(): void
    {
        $lock = $this->mutex->acquire($this->lockKey, $this->lockTtlSeconds);
        if ($lock === null) {
            $this->logger->info('Scheduled job skipped, previous run still holds the lock', ['lock' => $this->lockKey]);

            return;
        }

        try {
            $this->job->run();
        } finally {
            $this->mutex->release($lock);
        }
    }
}
