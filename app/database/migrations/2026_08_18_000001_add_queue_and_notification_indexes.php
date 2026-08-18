<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index on (queue, reserved_at) for faster job pickup.
        // The worker queries: WHERE queue = ? AND (reserved_at IS NULL OR reserved_at < ?)
        // ORDER BY available_at ASC LIMIT 1 — this index covers both columns.
        Schema::table('jobs', function (Blueprint $table): void {
            $table->index(['queue', 'reserved_at'], 'jobs_queue_reserved_at_index');
        });

        // Composite index on notifications for unread count + listing queries.
        // Already has idx_user_read and idx_user_created, but a covering index
        // for the most common query pattern (user_id + is_read + created_at) is faster.
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex('jobs_queue_reserved_at_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_user_read_created_index');
        });
    }
};
