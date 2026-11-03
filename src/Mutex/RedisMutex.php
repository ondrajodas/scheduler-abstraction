<?php

declare(strict_types=1);

namespace App\Mutex;

use Redis;
use RedisException;

final readonly class RedisMutex implements MutexInterface
{
    private const RELEASE_SCRIPT = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) end
        return 0
        LUA;

    public function __construct(
        private Redis $redis,
        private string $prefix = 'mutex:',
    ) {
    }

    public function acquire(string $key, int $ttlSeconds): ?Lock
    {
        $token = bin2hex(random_bytes(16));
        $acquired = $this->call(fn () => $this->redis->set($this->prefix . $key, $token, ['nx', 'px' => $ttlSeconds * 1000]));

        return $acquired === true ? new Lock($key, $token) : null;
    }

    public function release(Lock $lock): void
    {
        $this->call(fn () => $this->redis->eval(self::RELEASE_SCRIPT, [$this->prefix . $lock->key, $lock->token], 1));
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function call(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RedisException $e) {
            throw new MutexException('Redis mutex backend failure: ' . $e->getMessage(), 0, $e);
        }
    }
}
