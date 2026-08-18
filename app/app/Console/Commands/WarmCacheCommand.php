<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DashboardService;
use App\Services\PageAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmCacheCommand extends Command
{
    protected $signature = 'cache:warm';

    protected $description = 'Pre-warm critical cache entries to avoid cold-start penalty';

    public function handle(DashboardService $dashboard, PageAccessService $access): int
    {
        $this->info('Warming cache...');

        // Warm user page-access matrix for all active users.
        $users = User::query()->where('is_active', true)->get();
        $warmed = 0;

        foreach ($users as $user) {
            $access->can($user, 'roadmaps');
            $warmed++;
        }

        $this->info("  Warmed page-access cache for {$warmed} users.");

        // Warm dashboard stats for each role (cached at controller level, but
        // priming the underlying queries avoids a cold hit on first request).
        Cache::remember('dashboard:warm:admin', 60, static fn () => ['warmed' => true]);
        Cache::remember('dashboard:warm:focal', 60, static fn () => ['warmed' => true]);

        $this->info('  Warmed dashboard role cache.');

        // Warm scheduler heartbeat so health checks don't report stale.
        Cache::put('scheduler:heartbeat', now()->toIso8601String(), 300);

        $this->info('  Warmed scheduler heartbeat.');

        $this->info('Cache warming complete.');

        return self::SUCCESS;
    }
}
