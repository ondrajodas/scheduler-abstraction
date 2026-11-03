<?php

declare(strict_types=1);

namespace App\Tests\Mutex;

use App\Mutex\InMemoryMutex;
use App\Mutex\MutexInterface;
use App\Tests\Support\FixedClock;

final class InMemoryMutexTest extends MutexTestCase
{
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->clock = FixedClock::at('2026-11-03 10:00:00 UTC');
    }

    protected function createMutex(): MutexInterface
    {
        return new InMemoryMutex($this->clock);
    }

    protected function expire(int $ttlSeconds): void
    {
        $this->clock->moveTo($this->clock->now()->modify(sprintf('+%d seconds', $ttlSeconds))->format(DATE_ATOM));
    }
}
