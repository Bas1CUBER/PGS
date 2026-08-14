<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill user_page_access rows for users that have none.
     *
     * Page access became deny-by-default (CanAccessPageMiddleware) as part of
     * the security hardening; the legacy app allowed full access when no row
     * existed. Preserve the legacy behavior for existing accounts so no one
     * gets locked out on deploy.
     */
    public function up(): void
    {
        $missing = DB::table('users')
            ->select('id')
            ->whereNotIn('id', DB::table('user_page_access')->select('user_id'))
            ->pluck('id');

        $now = now();
        $rows = $missing->map(fn (int $userId): array => [
            'user_id' => $userId,
            'roadmaps' => true,
            'scorecard' => true,
            'performance_assessment' => true,
            'cascading' => true,
            'governance' => true,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('user_page_access')->insert($rows);
        }

        foreach ($missing as $userId) {
            DB::table('cache')->where('key', "pgs_access_{$userId}")->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
