<?php

declare(strict_types=1);

use App\Models\User;

it('locks out login attempts after five failures per minute', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

it('does not throttle successful logins', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();
    }
});
