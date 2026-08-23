<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliverables')) {
            return;
        }

        $rows = (int) DB::table('deliverables')->count();

        if ($rows > 0) {
            throw new RuntimeException("Refusing to drop non-empty legacy table 'deliverables' ({$rows} rows present).");
        }

        Schema::drop('deliverables');
    }

    public function down(): void
    {
        //
    }
};
