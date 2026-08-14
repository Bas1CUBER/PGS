<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Config-driven upload modules (legacy `*_uploads` tables).
 * One controller serves: resources, cascading activities, governance
 * culture/sharing, operations/strategy reviews, strategy refresh, and the
 * communication plan uploads. Statuses differ per module; some have none.
 */
final class UploadModuleRegistry
{
    /**
     * @return array<string, array{label: string, table: string, has_title: bool, has_description: bool, has_status: bool, status_values: list<string>|null, uploader_fk: string, uploader_label: string, singular: string, templates?: list<array{label: string, file: string, preview: bool}>}>
     */
    public static function modules(): array
    {
        return [
            'resources' => [
                'label' => 'Resources',
                'table' => 'resources_uploads',
                'has_title' => true,
                'has_description' => false,
                'has_status' => false,
                'status_values' => null,
                'uploader_fk' => 'uploaded_by',
                'uploader_label' => 'Uploaded by',
                'singular' => 'resource',
            ],
            'cascading-activities' => [
                'label' => 'Cascading Activities',
                'table' => 'cascading_activities',
                'has_title' => true,
                'has_description' => true,
                'has_status' => false,
                'status_values' => null,
                'uploader_fk' => 'uploaded_by',
                'uploader_label' => 'Uploaded by',
                'singular' => 'activity',
            ],
            'governance-culture' => [
                'label' => 'Governance: Culture',
                'table' => 'governance_culture_uploads',
                'has_title' => true,
                'has_description' => true,
                'has_status' => true,
                'status_values' => ['In Progress', 'Approved', 'Returned'],
                'uploader_fk' => 'employee_id',
                'uploader_label' => 'Uploaded by',
                'singular' => 'document',
            ],
            'governance-sharing' => [
                'label' => 'Governance: Sharing',
                'table' => 'governance_sharing_uploads',
                'has_title' => true,
                'has_description' => true,
                'has_status' => true,
                'status_values' => ['In Progress', 'Approved', 'Returned'],
                'uploader_fk' => 'employee_id',
                'uploader_label' => 'Uploaded by',
                'singular' => 'document',
            ],
            'operations-review' => [
                'label' => 'Operations Review',
                'table' => 'operations_review_uploads',
                'has_title' => false,
                'has_description' => false,
                'has_status' => true,
                'status_values' => ['Pending', 'Approved', 'Returned'],
                'uploader_fk' => 'employee_id',
                'uploader_label' => 'Submitted by',
                'singular' => 'submission',
                'templates' => [['label' => 'Operations Review Template and Process Flow', 'file' => 'TRC-LU OPERATIONS REVIEW TEMPLATE AND PROCESS FLOW.pdf', 'preview' => true]],
            ],
            'strategy-review' => [
                'label' => 'Strategy Review',
                'table' => 'strategy_review_uploads',
                'has_title' => true,
                'has_description' => false,
                'has_status' => true,
                'status_values' => ['Pending', 'Approved', 'Returned'],
                'uploader_fk' => 'employee_id',
                'uploader_label' => 'Submitted by',
                'singular' => 'submission',
                'templates' => [
                    ['label' => 'Strategy Review Template', 'file' => 'strategy_review_template.docx', 'preview' => false],
                    ['label' => 'Strategy Review Template and Process Flow', 'file' => 'TRC-LU STRATEGY REVIEW TEMPLATE AND PROCESS FLOW.pdf', 'preview' => true],
                ],
            ],
            'strategy-refresh' => [
                'label' => 'Strategy Refresh',
                'table' => 'strategy_refresh_uploads',
                'has_title' => true,
                'has_description' => false,
                'has_status' => false,
                'status_values' => null,
                'uploader_fk' => 'employee_id',
                'uploader_label' => 'Submitted by',
                'singular' => 'submission',
            ],
            'communication-plan' => [
                'label' => 'Communication Plan Uploads',
                'table' => 'communication_plan_uploads',
                'has_title' => false,
                'has_description' => false,
                'has_status' => true,
                'status_values' => ['Pending', 'Approved', 'Returned'],
                'uploader_fk' => 'employee_id',
                'uploader_label' => 'Submitted by',
                'singular' => 'submission',
                'templates' => [
                    ['label' => 'Communication Plan Template (PDF)', 'file' => 'Communication Plan Updated.docx.pdf', 'preview' => true],
                    ['label' => 'Communication Plan Template (DOCX)', 'file' => 'COMMUNICATION PLAN TEMPLATE.docx', 'preview' => false],
                ],
            ],
        ];
    }

    /**
     * @return array{slug: string, label: string, table: string, has_title: bool, has_description: bool, has_status: bool, status_values: list<string>|null, uploader_fk: string, uploader_label: string, singular: string, templates?: list<array{label: string, file: string, preview: bool}>}|null
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
