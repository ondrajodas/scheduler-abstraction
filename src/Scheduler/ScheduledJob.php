<?php

declare(strict_types=1);

namespace App\Scheduler;

use DateTimeZone;
use InvalidArgumentException;

final readonly class ScheduledJob
{
    private const NAME_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,127}$/';

    /**
     * @param string $name unique job name, also used as the lock key – keep it stable across deployments
     * @param string $cron crontab expression, e.g. '*\/15 * * * *' or '@daily'
     * @param int $lockTtlSeconds lock expiry when the process dies without releasing it
     * @param DateTimeZone|null $timezone timezone the cron expression is evaluated in (null = PHP default)
     */
    public function __construct(
        public string $name,
        public string $cron,
        public JobInterface $job,
        public bool $withoutOverlapping = true,
        public int $lockTtlSeconds = 3600,
        public ?DateTimeZone $timezone = null,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid job name "%s".', $name));
        }
        if ($lockTtlSeconds < 1) {
            throw new InvalidArgumentException('Lock TTL must be a positive number of seconds.');
        }
    }

    public function withJob(JobInterface $job): self
    {
        return new self($this->name, $this->cron, $job, $this->withoutOverlapping, $this->lockTtlSeconds, $this->timezone);
    }
}
