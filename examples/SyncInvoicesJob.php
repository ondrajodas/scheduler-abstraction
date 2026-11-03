<?php

declare(strict_types=1);

namespace App\Examples;

use App\Scheduler\JobInterface;
use Psr\Log\LoggerInterface;

final readonly class SyncInvoicesJob implements JobInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function run(): void
    {
        $this->logger->info('Syncing invoices');
    }
}
