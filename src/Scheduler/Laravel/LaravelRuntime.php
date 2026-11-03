<?php

declare(strict_types=1);

namespace App\Scheduler\Laravel;

use Illuminate\Container\Container;

/**
 * Minimal stand-in for the Laravel application that the scheduler's events expect as `$app`:
 * a container (to invoke callbacks) plus the maintenance-mode and environment checks of Event::isDue().
 */
final class LaravelRuntime extends Container
{
    public function __construct(
        private readonly string $environment = 'production',
    ) {
    }

    public function isDownForMaintenance(): bool
    {
        return false;
    }

    public function environment(): string
    {
        return $this->environment;
    }
}
