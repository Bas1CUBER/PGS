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

        Schema::table('audit_logs', function (Blueprint $table): void {
            DB::statement('UPDATE `audit_logs` SET `user_id` = 0 WHERE `user_id` IS NULL');
            $table->integer('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
