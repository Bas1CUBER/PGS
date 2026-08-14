<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPageAccess;
use Illuminate\Http\UploadedFile;

it('denies non-admin access to user management', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/users')->assertForbidden();
    $this->actingAs($user)->get('/users/create')->assertForbidden();
})->with(['employee', 'focal']);

it('lists users for admins with pagination', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data', 4));
});

it('searches users by email', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'needle@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($admin)
        ->get('/users?search=needle')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'needle@example.com'));
});

it('creates a user with page access and audits the action', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'New Staff',
            'email' => 'new@example.com',
            'password' => 'password-12345',
            'password_confirmation' => 'password-12345',
            'role' => 'focal',
            'office' => 'Planning',
            'roadmaps' => true,
            'scorecard' => false,
            'performance_assessment' => true,
            'cascading' => true,
            'governance' => true,
        ])
        ->assertRedirect('/users');

    $created = User::query()->where('email', 'new@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->role->value)->toBe('focal')
        ->and($created->email_verified_at)->not->toBeNull()
        ->and($created->pageAccess()->first()->scorecard)->toBeFalse();

    expect(AuditLog::query()->where('action', 'user.created')->exists())->toBeTrue();
});

it('validates user creation input', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'X',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'superadmin',
        ])
        ->assertSessionHasErrors(['email', 'password', 'role']);
});

it('updates a user and audits the change', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->employee()->create();

    $this->actingAs($admin)
        ->put("/users/{$target->id}", [
            'name' => 'Renamed',
            'email' => $target->email,
            'role' => 'focal',
            'office' => 'ICT',
            'is_active' => true,
        ])
        ->assertRedirect('/users');

    expect($target->fresh()->name)->toBe('Renamed')
        ->and($target->fresh()->role->value)->toBe('focal');

    expect(AuditLog::query()
        ->where('action', 'user.updated')
        ->where('resource_id', (string) $target->id)
        ->exists())->toBeTrue();
});

it('updates the page access matrix', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->employee()->create();
    $target->pageAccess()->create([
        'roadmaps' => true,
        'scorecard' => true,
        'performance_assessment' => true,
        'cascading' => true,
        'governance' => true,
    ]);

    $this->actingAs($admin)
        ->put("/users/{$target->id}/access", [
            'roadmaps' => false,
            'scorecard' => true,
            'performance_assessment' => false,
            'cascading' => false,
            'governance' => false,
        ])
        ->assertRedirect();

    expect(UserPageAccess::query()->find($target->id)->roadmaps)->toBeFalse();
});

it('toggles user activation', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->employee()->create();

    $this->actingAs($admin)
        ->post("/users/{$target->id}/toggle")
        ->assertRedirect();

    expect($target->fresh()->is_active)->toBeFalse();
});

it('cannot delete the own admin account', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete("/users/{$admin->id}")
        ->assertForbidden();

    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('imports users from a CSV with a report', function (): void {
    $admin = User::factory()->admin()->create();
    $csv = "email,password,role,name,office\n"
        ."imported@example.com,password-12345,employee,Imported User,ICT\n"
        ."bad-role@example.com,password-12345,superadmin,Bad Role,\n";

    $this->actingAs($admin)
        ->post('/users/import', [
            'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('created', 1);

    expect(User::query()->where('email', 'imported@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'bad-role@example.com')->exists())->toBeFalse();
});
