<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PageAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CanAccessPageMiddleware
{
    /**
     * Enforce the per-user module access matrix (legacy `user_page_access`),
     * cached for 60 seconds per user. Admins bypass the matrix; every other
     * account MUST have a matrix row — deny by default. The 2026_08_14
     * backfill migration provisions rows for all pre-existing users, and
     * both user creation paths (form + CSV import) create them explicitly.
     */
    public function __construct(private readonly PageAccessService $access) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        // No matrix row = no granted modules (matches the navbar, which
        // hides everything when PageAccessService::all() finds no row).
        if (! $this->access->hasMatrix($user)) {
            abort(403);
        }

        if (! $this->access->can($user, $module)) {
            abort(403);
        }

        return $next($request);
    }
}
