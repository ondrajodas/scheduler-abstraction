<?php

declare(strict_types=1);

namespace App\Tests\Mutex;

use App\Mutex\MutexException;
use App\Mutex\MutexInterface;
use App\Mutex\RedisMutex;
use Redis;

/**
 * Integration test against a real Redis (docker compose service "redis").
 */
final class RedisMutexTest extends MutexTestCase
{
    private Redis $redis;

    protected function setUp(): void
    {
        $host = getenv('REDIS_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('REDIS_HOST is not set, run the tests via docker compose.');
        }
        $this->redis = new Redis();
        $this->redis->connect($host);
        $this->redis->flushDB();
    }

    protected function createMutex(): MutexInterface
    {
        return new RedisMutex($this->redis);
    }

    protected function expire(int $ttlSeconds): void
    {
        usleep(($ttlSeconds * 1000 + 100) * 1000);
    }

    public function testUsesPrefixedKey(): void
    {
        (new RedisMutex($this->redis, 'app:'))->acquire('job', 60);

        self::assertSame(1, $this->redis->exists('app:job'));
    }

    public function testBackendFailureThrowsMutexException(): void
    {
        $disconnected = new Redis();

        $this->expectException(MutexException::class);
        (new RedisMutex($disconnected))->acquire('job', 60);
    }
}
