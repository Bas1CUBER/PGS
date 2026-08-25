<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

class RunBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    /**
     * Prevent a second queued backup from running while this one is
     * pending/active (the worker's retry window is longer than the job).
     */
    public function uniqueId(): string
    {
        return 'pgs-backup';
    }

    public function handle(): void
    {
        // Full backup: database AND files. The UI-triggered backup must cover
        // the same surface as the nightly scheduled backup.
        Artisan::call('backup:run');
    }
}
