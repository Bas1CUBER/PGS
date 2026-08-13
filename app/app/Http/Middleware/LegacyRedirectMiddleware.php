<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301-redirect legacy URLs (bookmarks, printed links) to their new routes.
 * Map extended during the dual-run watch window (docs/Phase_9.md).
 */
final class LegacyRedirectMiddleware
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        '/PGS' => '/dashboard',
        '/PGS/' => '/dashboard',
        '/PGS/employee_dashboard.php' => '/dashboard',
        '/PGS/focal_dashboard.php' => '/dashboard',
        '/PGS/admin_dashboard.php' => '/dashboard',
        '/PGS/employee_dashboard' => '/dashboard',
        '/PGS/focal_dashboard' => '/dashboard',
        '/PGS/admin_dashboard' => '/dashboard',
        '/PGS/login.php' => '/login',
        '/PGS/user_management.php' => '/users',
        '/PGS/notice.php' => '/notices',
        '/PGS/roadmap.php' => '/roadmaps',
        '/employee_dashboard.php' => '/dashboard',
        '/focal_dashboard.php' => '/dashboard',
        '/admin_dashboard.php' => '/dashboard',
        '/login.php' => '/login',
        '/user_management.php' => '/users',
        '/notice.php' => '/notices',
        '/roadmap.php' => '/roadmaps',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $target = self::targetFor('/'.ltrim($request->path(), '/'));

        if ($target !== null) {
            return redirect($target, 301);
        }

        return $next($request);
    }

    public static function targetFor(string $path): ?string
    {
        return self::MAP[$path] ?? null;
    }
}
