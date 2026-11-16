<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Mutex\MutexException;

/**
 * Keeps the job's lock alive. Long jobs call beat() regularly (e.g. after every batch);
 * it is cheap to call often, the lock backend is contacted only when the lock needs extending.
 */
interface LockHeartbeat
{
    /**
     * @throws LockLostException the lock expired or was taken over – stop the job, another run may be active
     * @throws MutexException
     */
    public function beat(): void;
}
