<?php

declare(strict_types=1);

namespace App\Mutex;

/**
 * Proof of ownership – only the holder of the token can release the lock.
 */
final readonly class Lock
{
    public function __construct(
        public string $key,
        public string $token,
    ) {
    }
}
