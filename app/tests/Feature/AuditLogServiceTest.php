<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AuditLogService;

it('records an audit log entry with context', function (): void {
    $user = User::factory()->admin()->create();

    $entry = app(AuditLogService::class)->record(
        $user->id,
        'user.updated',
        'user',
        (string) $user->id,
        ['role' => 'employee'],
        ['role' => 'focal'],
    );

    expect($entry->action)->toBe('user.updated')
        ->and($entry->resource_type)->toBe('user')
        ->and($entry->before)->toBe(['role' => 'employee'])
        ->and($entry->after)->toBe(['role' => 'focal'])
        ->and($entry->user_id)->toBe($user->id);
});

it('stores ip and user agent from the request', function (): void {
    $user = User::factory()->create();

    $entry = app(AuditLogService::class)->record(
        $user->id,
        'auth.login',
        'user',
        (string) $user->id,
        request: request(),
    );

    expect($entry->ip_address)->not->toBeNull()
        ->and($entry->user_agent)->not->toBeNull();
});
