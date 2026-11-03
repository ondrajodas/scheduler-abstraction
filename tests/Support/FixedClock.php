<?php

declare(strict_types=1);

namespace App\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {
    }

    public static function at(string $time): self
    {
        return new self(new DateTimeImmutable($time));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function moveTo(string $time): void
    {
        $this->now = new DateTimeImmutable($time);
    }
}
