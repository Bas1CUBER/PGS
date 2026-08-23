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
