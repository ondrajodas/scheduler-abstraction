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
        if (isset($this->locks[$key]) && $this->locks[$key]['expiresAt'] > $this->clock->now()) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $this->locks[$key] = ['token' => $token, 'expiresAt' => $this->expiresAt($ttlSeconds)];

        return new Lock($key, $token);
    }

    public function release(Lock $lock): void
    {
        if (($this->locks[$lock->key]['token'] ?? null) === $lock->token) {
            unset($this->locks[$lock->key]);
        }
    }

    public function extend(Lock $lock, int $ttlSeconds): bool
    {
        if (!$this->isHeld($lock)) {
            return false;
        }
        $this->locks[$lock->key]['expiresAt'] = $this->expiresAt($ttlSeconds);

        return true;
    }

    private function isHeld(Lock $lock): bool
    {
        $current = $this->locks[$lock->key] ?? null;

        return $current !== null && $current['token'] === $lock->token && $current['expiresAt'] > $this->clock->now();
    }

    private function expiresAt(int $ttlSeconds): DateTimeImmutable
    {
        return $this->clock->now()->modify(sprintf('+%d seconds', $ttlSeconds));
    }
}
