<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

it('requires authentication for the notifications page', function (): void {
    $this->get('/notifications')->assertRedirect('/login');
});

it('lists the authenticated users notifications', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'upload',
        'title' => 'Mine',
        'message' => 'x',
        'is_read' => false,
    ]);
    Notification::query()->create([
        'user_id' => $other->id,
        'type' => 'upload',
        'title' => 'Not mine',
        'message' => 'x',
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Mine'));
});

it('reports the unread count', function (): void {
    $user = User::factory()->create();
    Notification::query()->create(['user_id' => $user->id, 'type' => 'upload', 'title' => 'A', 'message' => 'a', 'is_read' => false]);
    Notification::query()->create(['user_id' => $user->id, 'type' => 'upload', 'title' => 'B', 'message' => 'b', 'is_read' => true]);

    $this->actingAs($user)
        ->getJson('/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('unread', 1);
});

it('marks a notification as read via the endpoint', function (): void {
    $user = User::factory()->create();
    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'upload',
        'title' => 'A',
        'message' => 'a',
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->post("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->is_read)->toBeTrue();
});

it('cannot mark another users notification via the endpoint', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $notification = Notification::query()->create([
        'user_id' => $owner->id,
        'type' => 'upload',
        'title' => 'A',
        'message' => 'a',
        'is_read' => false,
    ]);

    $this->actingAs($other)
        ->post("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->is_read)->toBeFalse();
});

it('marks all notifications as read via the endpoint', function (): void {
    $user = User::factory()->create();
    Notification::query()->create(['user_id' => $user->id, 'type' => 'upload', 'title' => 'A', 'message' => 'a', 'is_read' => false]);
    Notification::query()->create(['user_id' => $user->id, 'type' => 'upload', 'title' => 'B', 'message' => 'b', 'is_read' => false]);

    $this->actingAs($user)
        ->post('/notifications/read-all')
        ->assertRedirect();

    expect(Notification::query()->where('user_id', $user->id)->unread()->count())->toBe(0);
});
