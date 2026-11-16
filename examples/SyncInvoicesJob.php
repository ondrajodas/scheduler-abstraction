<?php

declare(strict_types=1);

namespace App\Examples;

use App\Scheduler\JobInterface;
use App\Scheduler\LockHeartbeat;
use Psr\Log\LoggerInterface;

final readonly class SyncInvoicesJob implements JobInterface
{
    private const PAGES = 3;

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function run(LockHeartbeat $heartbeat): void
    {
        $this->logger->info('Syncing invoices');
        for ($page = 1; $page <= self::PAGES; $page++) {
            $this->logger->info('Syncing invoices page', ['page' => $page]);
            // Keeps the lock alive when the sync takes longer than lockTtlSeconds.
            $heartbeat->beat();
        }
        $this->logger->info('Syncing invoices completed');
    }
}
