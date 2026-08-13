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
        '/gallery.php' => '/gallery',
        '/resources.php' => '/uploads/resources',
        '/resources_view.php' => '/uploads/resources',
        '/cascading_activities.php' => '/uploads/cascading-activities',
        '/cascading_activities_view.php' => '/uploads/cascading-activities',
        '/governance_culture.php' => '/uploads/governance-culture',
        '/governance_culture_view.php' => '/uploads/governance-culture',
        '/governance_sharing.php' => '/uploads/governance-sharing',
        '/governance_sharing_view.php' => '/uploads/governance-sharing',
        '/communication_plan.php' => '/communication-plan',
        '/communication_plan_view.php' => '/communication-plan',
        '/operations_review.php' => '/uploads/operations-review',
        '/operations_review_new.php' => '/uploads/operations-review',
        '/operations_review_view.php' => '/uploads/operations-review',
        '/strategy_review.php' => '/uploads/strategy-review',
        '/strategy_review_view.php' => '/uploads/strategy-review',
        '/strategy_refresh.php' => '/uploads/strategy-refresh',
        '/strategy_refresh_view.php' => '/uploads/strategy-refresh',
        '/impact_indicator.php' => '/impact-scorecard',
        '/survey.php' => '/surveys',
        '/about_strategy_map.php' => '/content/about-strategy-map',
        '/about_strategic_position.php' => '/content/about-strategic-position',
        '/about_pgs_pathway.php' => '/content/about-pgs-pathway',
        '/about_charter_statements.php' => '/content/about-charter-statements',
        '/about_user_access.php' => '/content/about-user-access',
        '/multi_sector_governance_system.php' => '/content/multi-sector-governance',
        '/office_for_strategy_management.php' => '/content/office-for-strategy-management',
        '/pgs_core_team.php' => '/content/pgs-core-team',
        '/annexb.php' => '/content/about-strategy-map',
        '/annexd.php' => '/content/about-strategy-map',
        '/annexe.php' => '/content/about-strategy-map',
        '/annexh.php' => '/content/about-strategy-map',
        '/annexj.php' => '/content/about-strategy-map',
        '/annexk.php' => '/content/about-strategy-map',
        '/OPCR.php' => '/content/about-strategy-map',
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
