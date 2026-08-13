<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;

it('creates a notification for a single user', function (): void {
    $user = User::factory()->create();

    $notification = app(NotificationService::class)->create(
        $user->id,
        NotificationType::Upload,
        'Deliverable uploaded',
        'A new file was uploaded.',
        42,
        'deliverable',
    );

    expect($notification)->not->toBeNull()
        ->and($notification->user_id)->toBe($user->id)
        ->and($notification->type)->toBe(NotificationType::Upload)
        ->and($notification->related_id)->toBe(42)
        ->and($notification->fresh()->is_read)->toBeFalse();
});

it('creates notifications for every user with a role', function (): void {
    User::factory()->focal()->count(3)->create();

    $created = app(NotificationService::class)->createForRole(
        'focal',
        NotificationType::Edit,
        'Template updated',
        'The template was updated.',
    );

    expect($created)->toBe(3)
        ->and(Notification::query()->where('type', NotificationType::Edit->value)->count())->toBe(3);
});

it('does not notify inactive users', function (): void {
    User::factory()->focal()->count(2)->create();
    User::factory()->focal()->inactive()->create();

    $created = app(NotificationService::class)->createForRole(
        'focal',
        NotificationType::Edit,
        'Title',
        'Message',
    );

    expect($created)->toBe(2);
});

it('deduplicates repeated user ids in bulk notifications', function (): void {
    $user = User::factory()->create();

    $created = app(NotificationService::class)->createForMany(
        [$user->id, $user->id, $user->id],
        NotificationType::Default,
        'Title',
        'Message',
    );

    expect($created)->toBe(1);
});

it('counts unread notifications', function (): void {
    $user = User::factory()->create();
    app(NotificationService::class)->create($user->id, NotificationType::Default, 'A', 'a');
    app(NotificationService::class)->create($user->id, NotificationType::Default, 'B', 'b');

    expect(app(NotificationService::class)->unreadCount($user->id))->toBe(2);
});

it('marks a single notification as read', function (): void {
    $user = User::factory()->create();
    $notification = app(NotificationService::class)->create($user->id, NotificationType::Default, 'A', 'a');

    expect(app(NotificationService::class)->markAsRead((int) $notification->id, $user->id))->toBeTrue()
        ->and($notification->fresh()->is_read)->toBeTrue();
});

it('cannot mark another users notification as read', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $notification = app(NotificationService::class)->create($owner->id, NotificationType::Default, 'A', 'a');

    expect(app(NotificationService::class)->markAsRead((int) $notification->id, $other->id))->toBeFalse()
        ->and($notification->fresh()->is_read)->toBeFalse();
});

it('marks all notifications as read', function (): void {
    $user = User::factory()->create();
    app(NotificationService::class)->create($user->id, NotificationType::Default, 'A', 'a');
    app(NotificationService::class)->create($user->id, NotificationType::Default, 'B', 'b');

    expect(app(NotificationService::class)->markAllAsRead($user->id))->toBe(2)
        ->and(app(NotificationService::class)->unreadCount($user->id))->toBe(0);
});
