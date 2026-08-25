<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\CacheInvalidationService;
use App\Services\NotificationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);

        // paginate() reads ?page= from the current request, so the page must
        // be part of the cache key or one page's result is served to all.
        $notifications = CacheInvalidationService::remember('notification', "index:{$user->id}:p".(int) $request->query('page', '1'), function () use ($user): array {
            return Notification::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->toArray();
        }, 60);

        $unreadCount = CacheInvalidationService::remember('notification', "unread:{$user->id}", function () use ($user): int {
            return $this->notifications->unreadCount($user->id);
        }, 30);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $this->userOrFail($request)->id;

        $count = CacheInvalidationService::remember('notification', "unread:{$userId}", function () use ($userId): int {
            return $this->notifications->unreadCount($userId);
        }, 30);

        return response()->json([
            'unread' => $count,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $this->userOrFail($request);

        $items = CacheInvalidationService::remember('notification', "feed:{$user->id}", function () use ($user): array {
            return Notification::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(static fn (Notification $notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->type->value,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at->toIso8601String(),
                ])
                ->all();
        }, 60);

        return response()->json(['data' => $items]);
    }

    public function markAsRead(Request $request, int $notification): RedirectResponse
    {
        $userId = $this->userOrFail($request)->id;
        $this->notifications->markAsRead($notification, $userId);

        CacheInvalidationService::onNotificationChange();

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $userId = $this->userOrFail($request)->id;
        $this->notifications->markAllAsRead($userId);

        CacheInvalidationService::onNotificationChange();

        return back();
    }

    /**
     * @throws AuthenticationException
     */
    private function userOrFail(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
