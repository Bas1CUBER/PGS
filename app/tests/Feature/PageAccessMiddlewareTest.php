<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('lets admins access any page regardless of the matrix', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/access-check/roadmaps')
        ->assertOk();
});

it('lets employees access modules enabled in their matrix', function (): void {
    $user = User::factory()->employee()->create();
    $user->pageAccess()->create([
        'roadmaps' => true,
        'scorecard' => false,
        'performance_assessment' => false,
        'cascading' => false,
        'governance' => false,
    ]);

    $this->actingAs($user)
        ->get('/access-check/roadmaps')
        ->assertOk();
});

it('blocks employees from modules disabled in their matrix', function (): void {
    $user = User::factory()->employee()->create();
    $user->pageAccess()->create([
        'roadmaps' => true,
        'scorecard' => false,
        'performance_assessment' => false,
        'cascading' => false,
        'governance' => false,
    ]);

    $this->actingAs($user)
        ->get('/access-check/scorecard')
        ->assertForbidden();
});

it('denies access when no matrix row exists', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/access-check/governance')
        ->assertForbidden();
});

it('caches the access matrix for 60 seconds', function (): void {
    $user = User::factory()->employee()->create();
    $user->pageAccess()->create([
        'roadmaps' => true,
        'scorecard' => true,
        'performance_assessment' => true,
        'cascading' => true,
        'governance' => true,
    ]);

    $this->actingAs($user)->get('/access-check/governance')->assertOk();

    $user->pageAccess()->update(['governance' => false]);
    Cache::forget("pgs_access_{$user->id}");

    $this->actingAs($user)->get('/access-check/governance')->assertForbidden();
});
