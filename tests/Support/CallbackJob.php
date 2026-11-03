<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Scheduler\JobInterface;
use Closure;

final class CallbackJob implements JobInterface
{
    public int $runs = 0;

    public function __construct(
        private readonly ?Closure $callback = null,
    ) {
    }

    public function run(): void
    {
        $this->runs++;
        if ($this->callback !== null) {
            ($this->callback)();
        }
    }
}
