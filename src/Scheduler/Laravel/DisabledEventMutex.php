<?php

declare(strict_types=1);

namespace App\Scheduler\Laravel;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use LogicException;

/**
 * Laravel's Event requires an EventMutex. Locking is done by App\Mutex,
 * so any use of Laravel's cache-based locking fails loudly.
 */
final class DisabledEventMutex implements EventMutex
{
    public function create(Event $event): bool
    {
        throw self::disabled();
    }

    public function exists(Event $event): bool
    {
        throw self::disabled();
    }

    public function forget(Event $event): void
    {
        throw self::disabled();
    }

    private static function disabled(): LogicException
    {
        return new LogicException('Laravel scheduler locking is disabled, use App\Mutex\MutexInterface.');
    }
}
