<?php

declare(strict_types=1);

namespace App\Tests\Mutex;

use App\Mutex\Lock;
use App\Mutex\MutexInterface;
use PHPUnit\Framework\TestCase;

/**
 * Contract every MutexInterface implementation must satisfy.
 */
abstract class MutexTestCase extends TestCase
{
    abstract protected function createMutex(): MutexInterface;

    /**
     * Makes locks acquired with $ttlSeconds expire.
     */
    abstract protected function expire(int $ttlSeconds): void;

    public function testSecondAcquireFailsWhileLocked(): void
    {
        $mutex = $this->createMutex();

        self::assertNotNull($mutex->acquire('job', 60));
        self::assertNull($mutex->acquire('job', 60));
    }

    public function testDifferentKeysDoNotBlockEachOther(): void
    {
        $mutex = $this->createMutex();

        self::assertNotNull($mutex->acquire('job-a', 60));
        self::assertNotNull($mutex->acquire('job-b', 60));
    }

    public function testReleaseAllowsNextAcquire(): void
    {
        $mutex = $this->createMutex();
        $lock = $mutex->acquire('job', 60);
        self::assertNotNull($lock);

        $mutex->release($lock);

        self::assertNotNull($mutex->acquire('job', 60));
    }

    public function testLockExpiresAfterTtl(): void
    {
        $mutex = $this->createMutex();
        $mutex->acquire('job', 1);

        $this->expire(1);

        self::assertNotNull($mutex->acquire('job', 60));
    }

    public function testReleaseWithForeignTokenKeepsLock(): void
    {
        $mutex = $this->createMutex();
        $mutex->acquire('job', 60);

        $mutex->release(new Lock('job', 'someone-elses-token'));

        self::assertNull($mutex->acquire('job', 60));
    }

    public function testExpiredOwnerCannotReleaseNewOwnersLock(): void
    {
        $mutex = $this->createMutex();
        $staleLock = $mutex->acquire('job', 1);
        self::assertNotNull($staleLock);
        $this->expire(1);
        $mutex->acquire('job', 60);

        $mutex->release($staleLock);

        self::assertNull($mutex->acquire('job', 60));
    }

    public function testReleaseIsIdempotent(): void
    {
        $mutex = $this->createMutex();
        $lock = $mutex->acquire('job', 60);
        self::assertNotNull($lock);

        $mutex->release($lock);
        $mutex->release($lock);

        self::assertNotNull($mutex->acquire('job', 60));
    }
}
