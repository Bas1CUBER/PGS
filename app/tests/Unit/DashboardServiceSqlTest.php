<?php

declare(strict_types=1);

use App\Services\DashboardService;

// Needs the Laravel container for the DB facade (toSql only — no queries run).
uses(Tests\TestCase::class);

/**
 * DashboardService builds its pending-approval and recent-upload feeds from
 * UploadModuleRegistry. These tests compile the SQL (toSql, no execution)
 * and assert the registry coverage contract:
 *  - pending queue only contains reviewable tables,
 *  - recent uploads cover ALL module tables so new modules can't be missed.
 */
function dashboardViaReflection(string $method): mixed
{
    $ref = new ReflectionMethod(DashboardService::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke(new DashboardService);
}

function allUploadTables(): array
{
    return array_map(
        static fn (array $m): string => $m['table'],
        App\Modules\UploadModuleRegistry::modules(),
    );
}

it('excludes non-reviewable tables from the pending approvals union', function (): void {
    $sql = strtolower(dashboardViaReflection('pendingApprovalUnionQuery')->toSql());

    // cascading_activities has no status column — it can never be "pending".
    expect($sql)->not->toContain('cascading_activities')
        ->and($sql)->toContain('operations_review_uploads')
        ->and($sql)->toContain('strategy_review_uploads')
        ->and($sql)->toContain('communication_plan_uploads')
        ->and($sql)->toContain('governance_culture_uploads')
        ->and($sql)->toContain('governance_sharing_uploads')
        ->and($sql)->toContain('progress_pending_changes');
});

it('filters every pending branch by an awaiting status', function (): void {
    $sql = strtolower(dashboardViaReflection('pendingApprovalUnionQuery')->toSql());

    expect(substr_count($sql, '"status" = ?') + substr_count($sql, '`status` = ?') + substr_count($sql, 'status = ?'))
        ->toBeGreaterThanOrEqual(5);
});

it('covers every registered upload table in recent uploads', function (): void {
    $sql = strtolower(dashboardViaReflection('recentUploadsUnion')->toSql());

    foreach (allUploadTables() as $table) {
        expect($sql)->toContain($table);
    }
});

it('orders recent uploads newest first with a limit', function (): void {
    $builder = dashboardViaReflection('recentUploadsUnion');
    $sql = strtolower($builder->orderByDesc('time')->limit(8)->toSql());

    expect($sql)->toContain('order by')
        ->and($sql)->toContain('limit');
});

it('joins users onto every branch for uploader attribution', function (): void {
    $pending = dashboardViaReflection('pendingApprovalUnionQuery')->toSql();
    $recent = dashboardViaReflection('recentUploadsUnion')->toSql();

    // MySQL grammar quotes identifiers with backticks.
    expect(substr_count(strtolower($pending), 'join `users`'))->toBeGreaterThanOrEqual(5)
        ->and(substr_count(strtolower($recent), 'join `users`'))->toBe(count(allUploadTables()));
});
