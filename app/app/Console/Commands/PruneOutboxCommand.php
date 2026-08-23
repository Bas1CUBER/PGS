<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOutboxCommand extends Command
{
    protected $signature = 'outbox:prune {--days=7 : Prune outbox mails older than this many days}';

    protected $description = 'Prune old rows from the outbox_mails log table to prevent unbounded growth';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = DB::table('outbox_mails')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} outbox mails older than {$days} days.");

        return self::SUCCESS;
    }
}
