<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Mutex\InMemoryMutex;
use App\Mutex\Lock;
use App\Scheduler\LockLostException;
use App\Scheduler\MutexHeartbeat;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class MutexHeartbeatTest extends TestCase
{
    private const TTL = 90;

    private FixedClock $clock;
    private InMemoryMutex $mutex;
    private Lock $lock;
    private MutexHeartbeat $heartbeat;

    protected function setUp(): void
    {
        $this->clock = FixedClock::at('2026-11-03 10:00:00 UTC');
        $this->mutex = new InMemoryMutex($this->clock);
        $lock = $this->mutex->acquire('job', self::TTL);
        self::assertNotNull($lock);
        $this->lock = $lock;
        $this->heartbeat = new MutexHeartbeat($this->mutex, $this->lock, self::TTL, $this->clock);
    }

    public function testExtendsLockOnceAThirdOfTtlHasPassed(): void
    {
        $this->clock->moveTo('2026-11-03 10:00:30 UTC');
        $this->heartbeat->beat();

        $this->clock->moveTo('2026-11-03 10:01:55 UTC');
        self::assertNull($this->mutex->acquire('job', 60));
    }

    public function testDoesNotExtendBeforeAThirdOfTtlHasPassed(): void
    {
        $this->clock->moveTo('2026-11-03 10:00:29 UTC');
        $this->heartbeat->beat();

        $this->clock->moveTo('2026-11-03 10:01:30 UTC');
        self::assertNotNull($this->mutex->acquire('job', 60));
    }

    public function testRegularBeatsKeepLockFarBeyondTtl(): void
    {
        foreach (['10:00:30', '10:01:00', '10:01:30', '10:02:00', '10:02:30'] as $time) {
            $this->clock->moveTo('2026-11-03 ' . $time . ' UTC');
            $this->heartbeat->beat();
        }

        self::assertNull($this->mutex->acquire('job', 60));
    }

    public function testThrowsWhenLockWasTakenOver(): void
    {
        $this->clock->moveTo('2026-11-03 10:01:31 UTC');
        $this->mutex->acquire('job', 60);

        $this->expectException(LockLostException::class);
        $this->heartbeat->beat();
    }
}
