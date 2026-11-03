<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Mutex\InMemoryMutex;
use App\Scheduler\LockingScheduler;
use App\Scheduler\SchedulerFactory;
use App\Tests\Support\FixedClock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SchedulerFactoryTest extends TestCase
{
    public function testCreatesLaravelSchedulerWrappedWithLocking(): void
    {
        self::assertInstanceOf(LockingScheduler::class, $this->factory()->create(SchedulerFactory::DRIVER_LARAVEL));
    }

    public function testRejectsUnknownDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown scheduler driver "cron-o-matic"');
        $this->factory()->create('cron-o-matic');
    }

    private function factory(): SchedulerFactory
    {
        $clock = FixedClock::at('now');

        return new SchedulerFactory(new InMemoryMutex($clock), new NullLogger(), $clock);
    }
}
