<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Mutex\Lock;
use App\Mutex\MutexInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Extends the lock to the full TTL once a third of the TTL has passed since the last extension,
 * so a missed beat or a slow backend call still leaves a safety margin.
 */
final class MutexHeartbeat implements LockHeartbeat
{
    private DateTimeImmutable $lastExtendedAt;

    public function __construct(
        private readonly MutexInterface $mutex,
        private readonly Lock $lock,
        private readonly int $ttlSeconds,
        private readonly ClockInterface $clock,
    ) {
        $this->lastExtendedAt = $clock->now();
    }

    public function beat(): void
    {
        $now = $this->clock->now();
        if (!$this->isExtensionDue($now)) {
            return;
        }
        if (!$this->mutex->extend($this->lock, $this->ttlSeconds)) {
            throw LockLostException::forKey($this->lock->key);
        }
        $this->lastExtendedAt = $now;
    }

    private function isExtensionDue(DateTimeImmutable $now): bool
    {
        $interval = max(1, intdiv($this->ttlSeconds, 3));

        return $now->getTimestamp() - $this->lastExtendedAt->getTimestamp() >= $interval;
    }
}
