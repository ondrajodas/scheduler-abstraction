<?php

declare(strict_types=1);

namespace App\Scheduler;

interface JobInterface
{
    /**
     * Throwing an exception marks the run as failed; other due jobs still run.
     *
     * Jobs that can run longer than their lock TTL must call $heartbeat->beat() regularly,
     * otherwise the lock expires and the next run overlaps with this one.
     */
    public function run(LockHeartbeat $heartbeat): void;
}
