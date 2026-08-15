<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\SectorDetailRegistry;
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
        '/roadmap_page_builder.php' => '/roadmaps',
        '/admin_backup_restore.php' => '/backups',
        '/admin_deadline.php' => '/deadlines',
        '/admin_survey.php' => '/surveys',
        '/change_password.php' => '/profile',
        '/employee_form.php' => '/deliverables/create',
        '/deliverable_add_form.php' => '/deliverables/create',
        '/employee_upload.php' => '/uploads',
        '/form.php' => '/deliverables/create',
        '/ajax_add_deliverable.php' => '/deliverables',
        '/get_deliverables.php' => '/deliverables',
        '/delete_deliverables.php' => '/deliverables',
        '/edit_deliverable.php' => '/deliverables',
        '/insert.php' => '/deliverables',
        '/update.php' => '/deliverables',
        '/gallery.php' => '/gallery',
        '/resources.php' => '/resources/upload',
        '/resources_view.php' => '/resources/upload',
        '/cascading_activities.php' => '/cascading-activities/upload',
        '/cascading_activities_view.php' => '/cascading-activities/upload',
        '/governance_culture.php' => '/governance-culture/upload',
        '/governance_culture_view.php' => '/governance-culture/upload',
        '/governance_sharing.php' => '/governance-sharing/upload',
        '/governance_sharing_view.php' => '/governance-sharing/upload',
        '/communication_plan.php' => '/communication-plan',
        '/communication_plan_view.php' => '/communication-plan',
        '/communication_plan_upload.php' => '/communication-plan/upload',
        '/communication_plan_update_status.php' => '/communication-plan/upload',
        '/communication_plan_roadmap_update_status.php' => '/communication-plan',
        '/operations_review.php' => '/operations-review',
        '/operations_review_new.php' => '/operations-review',
        '/operations_review_view.php' => '/operations-review/upload',
        '/operations_review_download.php' => '/operations-review/upload',
        '/operations_review_save.php' => '/operations-review',
        '/operations_review_template.php' => '/operations-review/upload',
        '/operations_review_update_status.php' => '/operations-review/upload',
        '/operations_review_upload.php' => '/operations-review/upload',
        '/strategy_review.php' => '/strategy-review/upload',
        '/strategy_review_view.php' => '/strategy-review/upload',
        '/strategy_review_form.php' => '/strategy-review',
        '/strategy_review_empty.php' => '/strategy-review/upload',
        '/strategy_review_generate_pdf.php' => '/strategy-review',
        '/strategy_review_save_draft.php' => '/strategy-review',
        '/strategy_review_update_status.php' => '/strategy-review/upload',
        '/strategy_review_upload.php' => '/strategy-review/upload',
        '/strategy_refresh.php' => '/strategy-refresh/upload',
        '/strategy_refresh_view.php' => '/strategy-refresh/upload',
        '/strategy_refresh_upload.php' => '/strategy-refresh/upload',
        '/impact_indicator.php' => '/impact-scorecard',
        '/impact_indicator_add_impact.php' => '/impact-scorecard',
        '/impact_indicator_add_year.php' => '/impact-scorecard',
        '/impact_indicator_delete_impact.php' => '/impact-scorecard',
        '/impact_indicator_delete_year.php' => '/impact-scorecard',
        '/impact_indicator_update.php' => '/impact-scorecard',
        '/add_notice.php' => '/notices',
        '/delete_notice.php' => '/notices',
        '/survey.php' => '/surveys',
        '/about_strategy_map.php' => '/content/about-strategy-map',
        '/about_strategic_position.php' => '/content/about-strategic-position',
        '/about_pgs_pathway.php' => '/content/about-pgs-pathway',
        '/about_charter_statements.php' => '/content/about-charter-statements',
        '/about_user_access.php' => '/content/about-user-access',
        '/multi_sector_governance_system.php' => '/content/multi-sector-governance',
        '/office_for_strategy_management.php' => '/content/office-for-strategy-management',
        '/pgs_core_team.php' => '/content/pgs-core-team',
        '/annexb.php' => '/annex/annex-b',
        '/annexd.php' => '/annex/annex-d',
        '/annexe.php' => '/annex/annex-e',
        '/annexh.php' => '/annex/annex-h',
        '/annexj.php' => '/annex/annex-j',
        '/annexk.php' => '/annex/annex-k',
        '/OPCR.php' => '/opcr',
        '/culture/culture.php' => '/sectors/culture',
        '/culture/roadmap_client_satisfaction.php' => '/sectors/culture/client-satisfaction',
        '/culture/roadmap_employee_engagement.php' => '/sectors/culture/employee-engagement',
        '/collab/collab.php' => '/sectors/collab',
        '/collab/roadmap_quality_of_life.php' => '/sectors/collab/quality-of-life',
        '/collab/roadmap_relapse_rate.php' => '/sectors/collab/relapse-rate',
        '/training/training.php' => '/sectors/training',
        '/training/roadmap_percentage_trained.php' => '/sectors/training/percentage-trained',
        '/training/roadmap_certified_tot.php' => '/sectors/training/certified-tot',
        '/technology/technology.php' => '/sectors/technology',
        '/technology/roadmap_patient_records_turnaround.php' => '/sectors/technology/patient-records',
        '/technology/roadmap_employee_records_turnaround.php' => '/sectors/technology/employee-records',
        '/research/research.php' => '/sectors/research',
        '/research/roadmap_outputs.php' => '/sectors/research/research-outputs',
        '/resilience/resilience.php' => '/sectors/resilience',
        '/resilience/roadmap_green_viability.php' => '/sectors/resilience/gvr',
        '/resilience/roadmap_reduced_adverse_events.php' => '/sectors/resilience/adverse-events',
        '/revenue/revenue.php' => '/sectors/revenue',
        '/revenue/roadmap_hospital_income.php' => '/sectors/revenue/hospital-income',
        '/revenue/roadmap_non_traditional_revenue.php' => '/sectors/revenue/non-traditional',
        '/user_access_get.php' => '/content/about-user-access',
        '/user_access_update.php' => '/content/about-user-access',
        '/user_add.php' => '/users',
        '/user_delete.php' => '/users',
        '/user_role_update.php' => '/users',
        '/user_toggle.php' => '/users',
        '/user_update.php' => '/users',
        '/users_import.php' => '/users',
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
        if (isset(self::MAP[$path])) {
            return self::MAP[$path];
        }

        $withoutPrefix = $path;
        if (str_starts_with($path, '/PGS/')) {
            $withoutPrefix = substr($path, strlen('/PGS'));
            if (isset(self::MAP[$withoutPrefix])) {
                return self::MAP[$withoutPrefix];
            }
        }

        $sectorPillars = ['collab', 'culture', 'research', 'resilience', 'revenue', 'technology', 'training'];
        foreach ($sectorPillars as $pillar) {
            if (preg_match("#^/{$pillar}/(?:add_year|delete_year|edit_year|get_year_data)\\.php$#", $withoutPrefix) === 1) {
                return "/sectors/{$pillar}";
            }
        }

        if (preg_match('#^/modules/(?:add_year|delete_year|edit_year|get_year_data|module_config)\\.php$#', $withoutPrefix) === 1) {
            return '/sectors';
        }

        // Pre-nesting sector detail URLs: /sector-details/{pillar}-{slug}
        // → /sectors/{pillar}/{slug}.
        if (str_starts_with($path, '/sector-details/')) {
            return SectorDetailRegistry::legacyTargetFor(substr($path, strlen('/sector-details/')));
        }

        return null;
    }
}
