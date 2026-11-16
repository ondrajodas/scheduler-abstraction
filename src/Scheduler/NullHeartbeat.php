<?php

declare(strict_types=1);

namespace App\Scheduler;

/**
 * Heartbeat for runs without a lock (withoutOverlapping: false).
 */
final readonly class NullHeartbeat implements LockHeartbeat
{
    public function beat(): void
    {
    }
}
