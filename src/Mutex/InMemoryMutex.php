<?php

declare(strict_types=1);

namespace App\Mutex;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Single-process implementation for tests.
 */
final class InMemoryMutex implements MutexInterface
{
    /** @var array<string, array{token: string, expiresAt: DateTimeImmutable}> */
    private array $locks = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function acquire(string $key, int $ttlSeconds): ?Lock
    {
        $now = $this->clock->now();
        if (isset($this->locks[$key]) && $this->locks[$key]['expiresAt'] > $now) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $this->locks[$key] = ['token' => $token, 'expiresAt' => $now->modify(sprintf('+%d seconds', $ttlSeconds))];

        return new Lock($key, $token);
    }

    public function release(Lock $lock): void
    {
        if (($this->locks[$lock->key]['token'] ?? null) === $lock->token) {
            unset($this->locks[$lock->key]);
        }
    }
}
