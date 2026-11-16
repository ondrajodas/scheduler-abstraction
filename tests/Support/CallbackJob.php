<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Scheduler\JobInterface;
use App\Scheduler\LockHeartbeat;
use Closure;

final class CallbackJob implements JobInterface
{
    public int $runs = 0;

    /**
     * @param (Closure(LockHeartbeat): mixed)|null $callback
     */
    public function __construct(
        private readonly ?Closure $callback = null,
    ) {
    }

    public function run(LockHeartbeat $heartbeat): void
    {
        $this->runs++;
        if ($this->callback !== null) {
            ($this->callback)($heartbeat);
        }
    }
}
