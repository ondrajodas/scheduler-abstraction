<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Mutex\MutexInterface;
use App\Scheduler\Laravel\LaravelScheduler;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Picks the scheduler implementation by name from configuration.
 * Adding an implementation (e.g. crunz) = one new match arm, consumers stay unchanged.
 */
final readonly class SchedulerFactory
{
    public const DRIVER_LARAVEL = 'laravel';

    public function __construct(
        private MutexInterface $mutex,
        private LoggerInterface $logger,
        // Laravel reads time from Carbon itself; kept for implementations evaluating at an explicit time (crunz).
        // @phpstan-ignore property.onlyWritten
        private ClockInterface $clock,
    ) {
    }

    public function create(string $driver): SchedulerInterface
    {
        $scheduler = match ($driver) {
            self::DRIVER_LARAVEL => new LaravelScheduler($this->logger),
            default => throw new InvalidArgumentException(sprintf('Unknown scheduler driver "%s".', $driver)),
        };

        return new LockingScheduler($scheduler, $this->mutex, $this->logger);
    }
}
