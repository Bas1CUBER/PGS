<?php

declare(strict_types=1);

use App\Models\User;

it('shares flash messages with authenticated users', function (): void {
    $user = User::factory()->employee()->create();

    session()->flash('success', 'Saved!');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('flash.success', 'Saved!')
            ->where('flash.error', null));
});

it('shares an empty flash shape without session messages', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('flash')
            ->where('flash.success', null));
});
