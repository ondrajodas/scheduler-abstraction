<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Scheduler\ScheduledJob;
use App\Tests\Support\CallbackJob;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScheduledJobTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['SyncInvoices'];
        yield 'space' => ['sync invoices'];
        yield 'too long' => [str_repeat('a', 129)];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduledJob($name, '* * * * *', new CallbackJob());
    }

    public function testRejectsNonPositiveLockTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduledJob('job', '* * * * *', new CallbackJob(), lockTtlSeconds: 0);
    }

    public function testWithJobKeepsOtherSettings(): void
    {
        $job = new ScheduledJob('job', '0 3 * * *', new CallbackJob(), false, 60);
        $replacement = new CallbackJob();

        $copy = $job->withJob($replacement);

        self::assertSame($replacement, $copy->job);
        self::assertSame(
            ['job', '0 3 * * *', false, 60],
            [$copy->name, $copy->cron, $copy->withoutOverlapping, $copy->lockTtlSeconds],
        );
    }
}
