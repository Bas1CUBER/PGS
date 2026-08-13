<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\DeadlineControl;
use App\Models\User;

it('denies non-admin access to deadline controls', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)->get('/deadlines')->assertForbidden();
});

it('lists deadline controls for admins', function (): void {
    $admin = User::factory()->admin()->create();
    DeadlineControl::query()->create([
        'role' => 'employee',
        'enabled' => false,
        'end_time' => null,
        'message' => 'default',
    ]);

    $this->actingAs($admin)
        ->get('/deadlines')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Deadlines/Index')
            ->has('deadlines', 1));
});

it('updates a deadline and clears the cache', function (): void {
    $admin = User::factory()->admin()->create();
    DeadlineControl::query()->create([
        'role' => 'employee',
        'enabled' => false,
        'end_time' => null,
        'message' => 'default',
    ]);

    $this->actingAs($admin)
        ->put('/deadlines/employee', [
            'enabled' => true,
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'message' => 'Submit by then.',
        ])
        ->assertRedirect();

    $deadline = DeadlineControl::query()->find('employee');
    expect($deadline->enabled)->toBeTrue()
        ->and($deadline->message)->toBe('Submit by then.');

    expect(AuditLog::query()->where('action', 'deadline.updated')->exists())->toBeTrue();
});

it('rejects a deadline end time in the past', function (): void {
    $admin = User::factory()->admin()->create();
    DeadlineControl::query()->create([
        'role' => 'focal',
        'enabled' => false,
        'end_time' => null,
        'message' => 'default',
    ]);

    $this->actingAs($admin)
        ->put('/deadlines/focal', [
            'enabled' => true,
            'end_time' => now()->subDay()->format('Y-m-d H:i:s'),
            'message' => 'Late',
        ])
        ->assertSessionHasErrors('end_time');
});
