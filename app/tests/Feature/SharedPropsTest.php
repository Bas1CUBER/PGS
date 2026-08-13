<?php

declare(strict_types=1);

use App\Models\DeadlineControl;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPageAccess;

it('shares the unread notification count with authenticated users', function (): void {
    $user = User::factory()->create();
    Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'upload',
        'title' => 'A',
        'message' => 'a',
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('unreadCount', 1));
});

it('shares the deadline state for employee roles', function (): void {
    $user = User::factory()->employee()->create();
    DeadlineControl::query()->create([
        'role' => 'employee',
        'enabled' => true,
        'end_time' => now()->addDays(3),
        'message' => 'Submit before the deadline.',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('deadline.enabled', true)
            ->has('deadline.end_time'));
});

it('does not share a deadline for admins', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('deadline', null));
});

it('seeds demo users with roles and page access', function (): void {
    $this->seed();

    expect(User::query()->where('email', 'admin@trcdoh.ph')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'focal@trcdoh.ph')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'employee@trcdoh.ph')->exists())->toBeTrue()
        ->and(UserPageAccess::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(DeadlineControl::query()->count())->toBe(2);
});
