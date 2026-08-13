<?php

declare(strict_types=1);

use App\Models\User;

it('allows the admin role on admin-only routes', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/role-check/admin-only')
        ->assertOk();
});

it('denies non-admin roles on admin-only routes', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/role-check/admin-only')
        ->assertForbidden();
})->with(['focal', 'employee']);

it('denies guests on role-protected routes', function (): void {
    $this->get('/role-check/admin-only')
        ->assertRedirect('/login');
});

it('allows any authenticated user through the role middleware when their role is listed', function (): void {
    $focal = User::factory()->focal()->create();

    $this->actingAs($focal)
        ->get('/role-check/focal')
        ->assertOk();

    $employee = User::factory()->employee()->create();

    $this->actingAs($employee)
        ->get('/role-check/focal')
        ->assertForbidden();
});
