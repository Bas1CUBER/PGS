<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneDeadJobsCommand extends Command
{
    protected $signature = 'queue:prune-dead {--hours=48 : Prune failed jobs older than this many hours}';

    protected $description = 'Prune old failed jobs from the database to prevent table bloat';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        if ($this->getOutput()->isVerbose()) {
            $before = DB::table('failed_jobs')->count();
        }

        $deleted = DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("Pruned {$deleted} failed jobs older than {$hours} hours.");

        return self::SUCCESS;
    }
}
