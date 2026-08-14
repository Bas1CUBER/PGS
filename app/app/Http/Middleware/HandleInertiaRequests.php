<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeadlineControl;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PageAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'unreadCount' => $user instanceof User
                ? app(NotificationService::class)->unreadCount($user->id)
                : 0,
            'deadline' => $user instanceof User && ! $user->isAdmin()
                ? $this->deadlineForRole($user->role->value)
                : null,
            'pageAccess' => $this->pageAccessFor($request),
        ];
    }

    /**
     * Per-user page access for the gated navbar groups (legacy
     * user_page_access rows, session-cached 60s — same as the old navbar).
     *
     * @return array<string, bool>
     */
    private function pageAccessFor(Request $request): array
    {
        $user = $request->user();

        return $user instanceof User
            ? app(PageAccessService::class)->all($user)
            : array_fill_keys(PageAccessService::MODULES, false);
    }

    /**
     * @return array{enabled: bool, end_time: string|null, message: string|null}|null
     */
    private function deadlineForRole(string $role): ?array
    {
        $deadline = Cache::remember(
            "pgs_deadline_{$role}",
            60,
            fn (): ?DeadlineControl => DeadlineControl::query()->find($role),
        );

        if ($deadline === null) {
            return null;
        }

        return [
            'enabled' => $deadline->enabled,
            'end_time' => $deadline->end_time?->toIso8601String(),
            'message' => $deadline->message,
        ];
    }
}
