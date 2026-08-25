<?php

declare(strict_types=1);

use App\Models\User;

it('deletes the account with the correct password', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::find($user->id))->toBeNull();
});

it('rejects account deletion with a wrong password', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(User::find($user->id))->not->toBeNull();
});

it('blocks the last active administrator from self-deleting', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete('/profile', ['password' => 'password'])
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

it('allows an administrator to self-delete when another active admin exists', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::find($admin->id))->toBeNull();
});
