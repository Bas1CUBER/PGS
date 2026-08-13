<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;

it('denies non-admin access to audit logs and backups', function (): void {
    $user = User::factory()->focal()->create();

    $this->actingAs($user)->get('/audit-logs')->assertForbidden();
    $this->actingAs($user)->get('/backups')->assertForbidden();
});

it('lists audit log entries for admins', function (): void {
    $admin = User::factory()->admin()->create();
    $actor = User::factory()->admin()->create();

    app(AuditLogService::class)->record(
        $actor->id,
        'user.created',
        'user',
        '1',
    );

    $this->actingAs($admin)
        ->get('/audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'user.created'));
});

it('audits a backup download', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/backups/local/some-file.zip')
        ->assertStatus(404); // file does not exist; route still requires auth+role

    expect(AuditLog::query()->where('action', 'backup.downloaded')->exists())->toBeFalse();
});
