<?php

declare(strict_types=1);

namespace App\Scheduler;

use InvalidArgumentException;

interface SchedulerInterface
{
    /**
     * @throws InvalidArgumentException when the cron expression is invalid or the name is already registered
     */
    public function add(ScheduledJob $job): void;

    /**
     * Runs all jobs due in the current minute. Call it once per minute from system cron.
     */
    public function runDue(): void;
}
