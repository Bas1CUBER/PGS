<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeadlineControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckDeadlineExpiryCommand extends Command
{
    protected $signature = 'deadline:check-expiry';

    protected $description = 'Auto-disable expired deadlines (end_time < now)';

    public function handle(): int
    {
        $expired = DeadlineControl::query()
            ->where('enabled', true)
            ->whereNotNull('end_time')
            ->where('end_time', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired deadlines found.');

            return self::SUCCESS;
        }

        foreach ($expired as $deadline) {
            $deadline->update(['enabled' => false]);
            Cache::forget("pgs_deadline_{$deadline->role}");
            $this->info("Disabled deadline for role: {$deadline->role}");
        }

        $this->info("Auto-disabled {$expired->count()} expired deadline(s).");

        return self::SUCCESS;
    }
}
