<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Config-driven sector detail roadmaps (the legacy `roadmap_*.php` pages).
 * Each entry maps a wide table: display columns + editable text columns
 * (year columns and free-form). One controller + one React page render all.
 */
final class SectorDetailRegistry
{
    /**
     * @return array<string, array{label: string, pillar: string, table: string, columns: list<string>, year_columns: list<string>, editable: list<string>}>
     */
    public static function modules(): array
    {
        return [
            'training-percentage-trained' => [
                'label' => 'Percentage of Personnel Trained',
                'pillar' => 'training',
                'table' => 'training_pct_personnel',
                'columns' => ['section', 'personnel', 'is_head'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['section', 'personnel'],
            ],
            'training-certified-tot' => [
                'label' => 'Certified Trainers (TOT)',
                'pillar' => 'training',
                'table' => 'training_tot_events',
                'columns' => ['serial_no', 'title', 'training_type', 'participants', 'date_label', 'year'],
                'year_columns' => [],
                'editable' => ['serial_no', 'title', 'training_type', 'participants', 'date_label', 'year'],
            ],
            'training-tot-personnel' => [
                'label' => 'TOT Personnel',
                'pillar' => 'training',
                'table' => 'training_tot_personnel',
                'columns' => ['section', 'personnel', 'is_head'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['section', 'personnel'],
            ],
            'resilience-adverse-events' => [
                'label' => 'Reduced Adverse Events',
                'pillar' => 'resilience',
                'table' => 'resilience_adverse_events',
                'columns' => ['label'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027'],
                'editable' => ['label'],
            ],
            'resilience-adverse-notes' => [
                'label' => 'Adverse Events Notes',
                'pillar' => 'resilience',
                'table' => 'resilience_adverse_notes',
                'columns' => ['label', 'val'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027'],
                'editable' => ['label', 'val'],
            ],
            'resilience-gvr' => [
                'label' => 'Green Viability Ratio',
                'pillar' => 'resilience',
                'table' => 'resilience_gvr',
                'columns' => ['indicator', 'share'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['indicator', 'share'],
            ],
            'revenue-hospital-income' => [
                'label' => 'Hospital Income',
                'pillar' => 'revenue',
                'table' => 'revenue_hospital_details',
                'columns' => ['label'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027'],
                'editable' => ['label'],
            ],
            'revenue-non-traditional' => [
                'label' => 'Non-Traditional Revenue',
                'pillar' => 'revenue',
                'table' => 'revenue_non_traditional',
                'columns' => ['classification'],
                'year_columns' => ['y2024', 'y2025', 'y2026', 'y2027', 'y2028'],
                'editable' => ['classification'],
            ],
            'culture-client-satisfaction' => [
                'label' => 'Client Satisfaction',
                'pillar' => 'culture',
                'table' => 'client_satisfaction_values',
                'columns' => ['table_key', 'division_key', 'year', 'annual'],
                'year_columns' => [],
                'editable' => ['table_key', 'division_key', 'year', 'annual'],
            ],
            'culture-employee-engagement' => [
                'label' => 'Employee Engagement',
                'pillar' => 'culture',
                'table' => 'engagement_values',
                'columns' => ['section_key', 'question_no', 'year', 'percent'],
                'year_columns' => [],
                'editable' => ['section_key', 'question_no', 'year', 'percent'],
            ],
            'collab-relapse-rate' => [
                'label' => 'Relapse Rate',
                'pillar' => 'collab',
                'table' => 'rr_summary_yearly',
                'columns' => ['year', 'grads_opd', 'grads_res', 'grads_after', 'relapse_opd', 'relapse_res', 'relapse_after'],
                'year_columns' => [],
                'editable' => ['grads_opd', 'grads_res', 'grads_after', 'relapse_opd', 'relapse_res', 'relapse_after'],
            ],
            'collab-quality-of-life' => [
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
            'technology-patient-records' => [
                'label' => 'Patient Records Turnaround',
                'pillar' => 'technology',
                'table' => 'patient_records_retrieval',
                'columns' => ['registry_no', 'request_date', 'request_time', 'released_date', 'released_time', 'returned_date', 'returned_time', 'retrieval_time'],
                'year_columns' => [],
                'editable' => ['registry_no', 'request_date', 'request_time', 'released_date', 'released_time', 'returned_date', 'returned_time', 'retrieval_time'],
            ],
            'technology-employee-records' => [
                'label' => 'Employee Records Turnaround',
                'pillar' => 'technology',
                'table' => 'employee_records_retrieval',
                'columns' => ['registry_no', 'request_date', 'request_time', 'released_date', 'released_time', 'returned_date', 'returned_time', 'retrieval_time'],
                'year_columns' => [],
                'editable' => ['registry_no', 'request_date', 'request_time', 'released_date', 'released_time', 'returned_date', 'returned_time', 'retrieval_time'],
            ],
        ];
    }

    /**
     * @return array{slug: string, label: string, pillar: string, table: string, columns: list<string>, year_columns: list<string>, editable: list<string>}|null
     */
    public static function find(string $slug): ?array
    {
        $module = self::modules()[$slug] ?? null;

        if ($module === null) {
            return null;
        }

        return ['slug' => $slug] + $module;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::modules());
    }
}
