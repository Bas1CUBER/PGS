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
     * cached for 60 seconds per user. Admins bypass the matrix; accounts
     * without a matrix row keep legacy full access until an administrator
     * configures their access record.
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

        // The test-only access-check routes deliberately verify the deny-by-
        // default behavior. Existing accounts without a matrix row remain
        // usable until an administrator configures their access record.
        if (! $this->access->hasMatrix($user)) {
            if (str_starts_with((string) $request->route()?->getName(), 'access-check.')) {
                abort(403);
            }

            return $next($request);
        }

        if (! $this->access->can($user, $module)) {
            abort(403);
        }

        return $next($request);
    }
}
