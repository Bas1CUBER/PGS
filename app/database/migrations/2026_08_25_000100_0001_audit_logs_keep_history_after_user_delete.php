<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            // Audit history must outlive the account: deleting a user keeps
            // their log entries (user_id becomes NULL) instead of wiping them.
            $table->integer('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        // Reverting "keep history after user delete" necessarily discards
        // log entries whose account is already gone: the legacy schema had a
        // NOT NULL user_id with cascade delete, so such rows could never
        // exist before this migration. Deleting them here (instead of the
        // broken user_id=0 update) keeps InnoDB's FK validation happy.
        DB::table('audit_logs')->whereNull('user_id')->delete();

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->integer('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
