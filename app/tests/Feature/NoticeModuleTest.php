<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Notice;
use App\Models\User;

it('lists notices for authenticated users', function (): void {
    $user = User::factory()->employee()->create();
    Notice::query()->create(['title' => 'Maintenance window']);

    $this->actingAs($user)
        ->get('/notices')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notices/Index')
            ->has('notices.data', 1));
});

it('requires authentication for notices', function (): void {
    $this->get('/notices')->assertRedirect('/login');
});

it('creates a notice', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->post('/notices', [
            'title' => 'System update',
            'description' => 'Scheduled maintenance on Friday.',
        ])
        ->assertRedirect();

    expect(Notice::query()->where('title', 'System update')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'notice.created')->exists())->toBeTrue();
});

it('updates and deletes a notice', function (): void {
    $user = User::factory()->admin()->create();
    $notice = Notice::query()->create(['title' => 'Old', 'description' => 'x']);

    $this->actingAs($user)
        ->put("/notices/{$notice->notice_id}", [
            'title' => 'New title',
            'description' => 'y',
        ])
        ->assertRedirect();

    expect($notice->fresh()->title)->toBe('New title');

    $this->actingAs($user)
        ->delete("/notices/{$notice->notice_id}")
        ->assertRedirect();

    expect(Notice::query()->find($notice->notice_id))->toBeNull();
});
