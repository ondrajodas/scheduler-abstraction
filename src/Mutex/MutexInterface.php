<?php

declare(strict_types=1);

namespace App\Mutex;

/**
 * Distributed lock. Every implementation must guarantee:
 *  - acquire is atomic across all processes and servers sharing the backend
 *  - every lock expires after its TTL (no dead locks after kill -9 / OOM / deploy)
 *  - release and extend touch only the caller's own lock (token check)
 *  - backend failure throws MutexException, it is never treated as "acquired"
 */
interface MutexInterface
{
    /**
     * @return Lock|null null when the lock is held by someone else
     * @throws MutexException
     */
    public function acquire(string $key, int $ttlSeconds): ?Lock;

    /**
     * Idempotent; an expired or foreign lock is left untouched.
     *
     * @throws MutexException
     */
    public function release(Lock $lock): void;

    /**
     * Resets the lock's expiry to $ttlSeconds from now. An expired lock is never re-created.
     *
     * @return bool false when the lock is no longer held by the caller (expired or taken over)
     * @throws MutexException
     */
    public function extend(Lock $lock, int $ttlSeconds): bool;
}
