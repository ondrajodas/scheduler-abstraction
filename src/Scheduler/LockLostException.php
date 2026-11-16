<?php

declare(strict_types=1);

namespace App\Scheduler;

use RuntimeException;

final class LockLostException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Lock "%s" was lost, the job must not continue.', $key));
    }
}
