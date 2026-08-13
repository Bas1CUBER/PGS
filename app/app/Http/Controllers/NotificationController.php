<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
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

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $this->notifications->unreadCount($user->id),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $this->notifications->unreadCount($this->userOrFail($request)->id),
        ]);
    }

    public function markAsRead(Request $request, int $notification): RedirectResponse
    {
        $this->notifications->markAsRead($notification, $this->userOrFail($request)->id);

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllAsRead($this->userOrFail($request)->id);

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
