<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class CanAccessPageMiddleware
{
    /**
     * Enforce the per-user module access matrix (legacy `user_page_access`),
     * cached for 60 seconds per user. Admins bypass the matrix.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isAdmin()) {
            return $next($request);
        }

        $access = Cache::remember(
            "pgs_access_{$user->id}",
            60,
            fn (): array => $this->loadAccess($user),
        );

        if (! ($access[$module] ?? false)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * @return array<string, bool>
     */
    private function loadAccess(User $user): array
    {
        $row = $user->pageAccess()->first();

        return [
            'roadmaps' => $row === null ? true : $row->roadmaps,
            'scorecard' => $row === null ? true : $row->scorecard,
            'performance_assessment' => $row === null ? true : $row->performance_assessment,
            'cascading' => $row === null ? true : $row->cascading,
            'governance' => $row === null ? true : $row->governance,
        ];
    }
}
