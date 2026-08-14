<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Config-driven sector detail roadmaps (the legacy `roadmap_*.php` pages).
 * Each entry maps a wide table: display columns + editable text columns
 * (year columns and free-form). One controller + one React page render all.
 *
 * Slugs are pillar-scoped (e.g. `/sectors/collab/relapse-rate`): the same
 * slug may exist under different pillars, so lookups always match pillar +
 * slug together.
 */
final class SectorDetailRegistry
{
    /**
     * @return array<string, array{label: string, pillar: string, table: string, columns: list<string>, year_columns: list<string>, editable: list<string>}>
     */
    public static function modules(): array
    {
        return [
            'percentage-trained' => [
                'label' => 'Percentage of Personnel Trained',
                'pillar' => 'training',
                'table' => 'training_pct_personnel',
                'columns' => ['section', 'personnel', 'is_head'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['section', 'personnel'],
            ],
            'certified-tot' => [
                'label' => 'Certified Trainers (TOT)',
                'pillar' => 'training',
                'table' => 'training_tot_events',
                'columns' => ['serial_no', 'title', 'training_type', 'participants', 'date_label', 'year'],
                'year_columns' => [],
                'editable' => ['serial_no', 'title', 'training_type', 'participants', 'date_label', 'year'],
            ],
            'tot-personnel' => [
                'label' => 'TOT Personnel',
                'pillar' => 'training',
                'table' => 'training_tot_personnel',
                'columns' => ['section', 'personnel', 'is_head'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['section', 'personnel'],
            ],
            'adverse-events' => [
                'label' => 'Reduced Adverse Events',
                'pillar' => 'resilience',
                'table' => 'resilience_adverse_events',
                'columns' => ['category', 'type'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027'],
                'editable' => ['category', 'type'],
            ],
            'adverse-notes' => [
                'label' => 'Adverse Events Notes',
                'pillar' => 'resilience',
                'table' => 'resilience_adverse_notes',
                'columns' => ['label', 'val'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027'],
                'editable' => ['label', 'val'],
            ],
            'gvr' => [
                'label' => 'Green Viability Ratio',
                'pillar' => 'resilience',
                'table' => 'resilience_gvr',
                'columns' => ['indicator', 'share'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['indicator', 'share'],
            ],
            'hospital-income' => [
                'label' => 'Hospital Income',
                'pillar' => 'revenue',
                'table' => 'revenue_hospital_details',
                'columns' => ['label'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027'],
                'editable' => ['label'],
            ],
            'non-traditional' => [
                'label' => 'Non-Traditional Revenue',
                'pillar' => 'revenue',
                'table' => 'revenue_non_traditional',
                'columns' => ['classification'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['classification'],
            ],
            'client-satisfaction' => [
                'label' => 'Client Satisfaction',
                'pillar' => 'culture',
                'table' => 'client_satisfaction_values',
                'columns' => ['table_key', 'division_key', 'year', 'annual'],
                'year_columns' => [],
                'editable' => ['table_key', 'division_key', 'year', 'annual'],
            ],
            'employee-engagement' => [
                'label' => 'Employee Engagement',
                'pillar' => 'culture',
                'table' => 'engagement_values',
                'columns' => ['section_key', 'question_no', 'year', 'percent'],
                'year_columns' => [],
                'editable' => ['section_key', 'question_no', 'year', 'percent'],
            ],
            'relapse-rate' => [
                'label' => 'Relapse Rate',
                'pillar' => 'collab',
                'table' => 'rr_summary_yearly',
                'columns' => ['year', 'grads_opd', 'grads_res', 'grads_after', 'relapse_opd', 'relapse_res', 'relapse_after'],
                'year_columns' => [],
                'editable' => ['grads_opd', 'grads_res', 'grads_after', 'relapse_opd', 'relapse_res', 'relapse_after'],
            ],
            'quality-of-life' => [
                'label' => 'Quality of Life',
                'pillar' => 'collab',
                'table' => 'qli_employment_rows',
                'columns' => ['registry_no', 'name', 'program', 'entry_employment', 'entry_occupation', 'after_employment', 'after_occupation', 'remarks'],
                'year_columns' => [],
                'editable' => ['registry_no', 'name', 'program', 'entry_employment', 'entry_occupation', 'after_employment', 'after_occupation', 'remarks'],
            ],
            'research-outputs' => [
                'label' => 'Research Outputs',
                'pillar' => 'research',
                'table' => 'research_outputs',
                'columns' => ['research_no', 'title', 'topic', 'target_year', 'phase_status', 'outcome_status'],
                'year_columns' => [],
                'editable' => ['research_no', 'title', 'topic', 'target_year', 'phase_status', 'outcome_status'],
            ],
            'patient-records' => [
                'label' => 'Patient Records Turnaround',
                'pillar' => 'technology',
                'table' => 'patient_records_retrieval',
                'columns' => ['registry_no', 'request_date', 'request_time', 'released_date', 'released_time', 'returned_date', 'returned_time', 'retrieval_time'],
                'year_columns' => [],
                'editable' => ['registry_no', 'request_date', 'request_time', 'released_date', 'released_time', 'returned_date', 'returned_time', 'retrieval_time'],
            ],
            'employee-records' => [
                'label' => 'Employee Records Turnaround',
                'pillar' => 'technology',
                'table' => 'employee_records_retrieval',
                'columns' => ['staff_name', 'request_date', 'request_time', 'released_date', 'released_time', 'retrieval_time'],
                'year_columns' => [],
                'editable' => ['staff_name', 'request_date', 'request_time', 'released_date', 'released_time', 'retrieval_time'],
            ],
        ];
    }

    /**
     * @return array{slug: string, label: string, pillar: string, table: string, columns: list<string>, year_columns: list<string>, editable: list<string>}|null
     */
    public static function find(string $pillar, string $slug): ?array
    {
        $module = self::modules()[$slug] ?? null;

        if ($module === null || $module['pillar'] !== $pillar) {
            return null;
        }

        return ['slug' => $slug] + $module;
    }

    /**
     * @return list<array{slug: string, label: string}>
     */
    public static function forPillar(string $pillar): array
    {
        $details = [];

        foreach (self::modules() as $slug => $module) {
            if ($module['pillar'] === $pillar) {
                $details[] = ['slug' => $slug, 'label' => $module['label']];
            }
        }

        return $details;
    }

    /**
     * Resolve the pre-nesting URL format (`/sector-details/{pillar}-{slug}`)
     * to the current nested route (`/sectors/{pillar}/{slug}`) so old
     * bookmarks keep working.
     */
    public static function legacyTargetFor(string $legacySlug): ?string
    {
        foreach (self::modules() as $slug => $module) {
            if ("{$module['pillar']}-{$slug}" === $legacySlug) {
                return "/sectors/{$module['pillar']}/{$slug}";
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::modules());
    }

    public static function logoFor(string $pillar, string $slug): string
    {
        return match ($slug) {
            'client-satisfaction', 'employee-engagement' => 'employee_logo.png',
            'relapse-rate', 'quality-of-life' => 'roadmap1.png',
            'percentage-trained', 'certified-tot', 'tot-personnel' => 'training_logo.png',
            'patient-records', 'employee-records' => 'patientR_logo.png',
            'research-outputs' => 'research_logo.png',
            'adverse-events', 'adverse-notes', 'gvr' => 'resilience_logo.png',
            'hospital-income', 'non-traditional' => 'revenue_logo.png',
            default => SectorModuleRegistry::find($pillar)['logo'] ?? 'pgs_logo.png',
        };
    }
}
