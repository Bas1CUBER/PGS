<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Config-driven sector roadmap modules (docs/Phase_7.md).
 *
 * All seven pillars share identical shapes (verified against the live
 * schema): main table (id, category, year, description), a progress tracker
 * (id, category, year, month, status, remarks, updated_by), and optionally a
 * schedule (id, category, year, month, description). One controller renders
 * any pillar from this config.
 */
final class SectorModuleRegistry
{
    /**
     * @return array<string, array{label: string, table: string, progress_table: string, schedule_table: string|null}>
     */
    public static function modules(): array
    {
        return [
            'culture' => [
                'label' => 'Culture of Organization',
                'table' => 'culture',
                'progress_table' => 'culture_progress',
                'schedule_table' => null,
            ],
            'collab' => [
                'label' => 'Collaborative Healthcare Management',
                'table' => 'collab',
                'progress_table' => 'collab_progress',
                'schedule_table' => 'collab_schedule',
            ],
            'training' => [
                'label' => 'Training',
                'table' => 'training',
                'progress_table' => 'training_progress',
                'schedule_table' => null,
            ],
            'technology' => [
                'label' => 'Technology',
                'table' => 'technology',
                'progress_table' => 'technology_progress',
                'schedule_table' => null,
            ],
            'research' => [
                'label' => 'Research',
                'table' => 'research',
                'progress_table' => 'research_progress',
                'schedule_table' => 'research_schedule',
            ],
            'revenue' => [
                'label' => 'Revenue',
                'table' => 'revenue',
                'progress_table' => 'revenue_progress',
                'schedule_table' => null,
            ],
            'resilience' => [
                'label' => 'Resilience',
                'table' => 'resilience',
                'progress_table' => 'resilience_progress',
                'schedule_table' => null,
            ],
        ];
    }

    /**
     * @return array{slug: string, label: string, table: string, progress_table: string, schedule_table: string|null}|null
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
