<?php

declare(strict_types=1);

namespace App\Scheduler\Laravel;

use App\Scheduler\ScheduledJob;
use App\Scheduler\SchedulerInterface;
use Illuminate\Console\Scheduling\CallbackEvent;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Runs jobs through Laravel's scheduler events (CallbackEvent) without a Laravel application.
 *
 * Laravel's own locking (withoutOverlapping/onOneServer) is never enabled – locking is done
 * engine-independently by LockingScheduler, so DisabledEventMutex is only a safety net.
 */
final class LaravelScheduler implements SchedulerInterface
{
    /** @var array<string, CallbackEvent> */
    private array $events = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LaravelRuntime $runtime = new LaravelRuntime(),
    ) {
    }

    public function add(ScheduledJob $job): void
    {
        if (isset($this->events[$job->name])) {
            throw new InvalidArgumentException(sprintf('Job "%s" is already registered.', $job->name));
        }
        $this->events[$job->name] = $this->createEvent($job);
    }

    public function runDue(): void
    {
        // Select due events up front (like `artisan schedule:run`), so a long job
        // does not shift the evaluation time of the jobs after it.
        foreach ($this->dueEvents() as $name => $event) {
            $this->runEvent($name, $event);
        }
    }

    /**
     * @return array<string, CallbackEvent>
     */
    private function dueEvents(): array
    {
        // Laravel types $app as the full Application; the runtime provides everything isDue/filtersPass use.
        return array_filter(
            $this->events,
            // @phpstan-ignore argument.type, argument.type
            fn (CallbackEvent $event) => $event->isDue($this->runtime) && $event->filtersPass($this->runtime),
        );
    }

    private function createEvent(ScheduledJob $job): CallbackEvent
    {
        // cron-expression accepts only a timezone name, not a DateTimeZone instance
        $event = new CallbackEvent(new DisabledEventMutex(), [$job->job, 'run'], [], $job->timezone?->getName());
        try {
            $event->cron($job->cron)->name($job->name)->nextRunDate();
        } catch (InvalidArgumentException | RuntimeException) {
            throw new InvalidArgumentException(sprintf('Invalid cron expression "%s" for job "%s".', $job->cron, $job->name));
        }

        return $event;
    }

    private function runEvent(string $name, CallbackEvent $event): void
    {
        try {
            $event->run($this->runtime);
        } catch (Throwable $e) {
            $this->logger->error('Scheduled job failed', ['job' => $name, 'exception' => $e]);
        }
    }
}
