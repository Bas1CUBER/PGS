<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PageAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OptionalCanAccessPageMiddleware
{
    public function __construct(private readonly PageAccessService $access) {}

    /**
     * Enforce configured access rows while keeping accounts created before
     * the access matrix migration usable until an administrator configures a
     * row for them.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->access->hasMatrix($user)) {
            return $next($request);
        }

        abort_unless($this->access->can($user, $module), 403);

        return $next($request);
    }
}
