<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class NotificationService
{
    /**
     * Create a notification for a single user.
     */
    public function create(
        int $userId,
        NotificationType $type,
        string $title,
        string $message,
        ?int $relatedId = null,
        ?string $relatedType = null,
    ): ?Notification {
        if (! User::query()->whereKey($userId)->exists()) {
            return null;
        }

        return Notification::query()->create([
            'user_id' => $userId,
            'type' => $type->value,
            'title' => $title,
            'message' => $message,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
        ]);
    }

    /**
     * Create the same notification for many users.
     *
     * @param  list<int>  $userIds
     */
    public function createForMany(
        array $userIds,
        NotificationType $type,
        string $title,
        string $message,
        ?int $relatedId = null,
        ?string $relatedType = null,
    ): int {
        $userIds = array_values(array_unique(array_map(static fn (int $id): int => $id, $userIds)));

        if ($userIds === []) {
            return 0;
        }

        $userIds = User::query()
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($userIds === []) {
            return 0;
        }

        $now = now()->toDateTimeString();
        $rows = array_map(
            static fn (int $uid): array => [
                'user_id' => $uid,
                'type' => $type->value,
                'title' => $title,
                'message' => $message,
                'related_id' => $relatedId,
                'related_type' => $relatedType,
                'is_read' => 0,
                'created_at' => $now,
            ],
            $userIds,
        );

        return DB::table('notifications')->insertOrIgnore($rows);
    }

    /**
     * Create notifications for every user with the given role.
     */
    public function createForRole(
        string $role,
        NotificationType $type,
        string $title,
        string $message,
        ?int $relatedId = null,
        ?string $relatedType = null,
    ): int {
        $ids = User::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $userIds = array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids,
        ));

        return $this->createForMany(
            $userIds,
            $type,
            $title,
            $message,
            $relatedId,
            $relatedType,
        );
    }

    /**
     * Create notifications for several roles, excluding the actor who caused
     * the event. This keeps workflow notices useful without notifying the
     * submitter about their own action.
     *
     * @param  list<string>  $roles
     */
    public function createForRolesExcept(
        array $roles,
        int $exceptUserId,
        NotificationType $type,
        string $title,
        string $message,
        ?int $relatedId = null,
        ?string $relatedType = null,
    ): int {
        $ids = User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->where('id', '<>', $exceptUserId)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $this->createForMany(array_values($ids), $type, $title, $message, $relatedId, $relatedType);
    }

    public function unreadCount(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->unread()
            ->count();
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification === null) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->unread()
            ->update(['is_read' => true]);
    }
}
