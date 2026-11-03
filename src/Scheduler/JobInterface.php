<?php

declare(strict_types=1);

namespace App\Scheduler;

interface JobInterface
{
    /**
     * Throwing an exception marks the run as failed; other due jobs still run.
     */
    public function run(): void;
}
