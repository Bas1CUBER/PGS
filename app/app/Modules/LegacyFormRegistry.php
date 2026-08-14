<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Current, authenticated destinations for the legacy Annex workbooks.
 *
 * The original repository tracked the PHP wrappers but not the referenced
 * `forms/Annex *.html` and `.xlsx` artifacts. These entries preserve the
 * legacy destinations as usable, downloadable workspace views instead of
 * redirecting them to an unrelated content page.
 */
final class LegacyFormRegistry
{
    /**
     * @return array<string, array{slug: string, title: string, description: string, columns: list<string>, source_note: string, editable: bool}>
     */
    public static function annexes(): array
    {
        return [
            'annex-b' => [
                'slug' => 'annex-b',
                'title' => 'Annex B — Strategy Map',
                'description' => 'Reference workspace for strategy-map objectives, measures, and accountable units.',
                'columns' => ['Perspective', 'Strategic objective', 'Measure', 'Accountable unit'],
                'editable' => true,
                'source_note' => 'The original Annex B workbook was referenced by the legacy PHP wrapper but was not present in the source archive. This workspace keeps the destination available and ready for current strategy-map data.',
            ],
            'annex-d' => [
                'slug' => 'annex-d',
                'title' => 'Annex D — Strategic Performance Targets',
                'description' => 'A read-through of the current annual and quarterly performance-target register.',
                'columns' => ['Strategic goal', 'Success indicator', 'Division accountable', 'Annual target'],
                'editable' => false,
                'source_note' => 'Rows are read from the current OPCR target register so this Annex stays aligned with the maintained performance data.',
            ],
            'annex-e' => [
                'slug' => 'annex-e',
                'title' => 'Annex E — Quarterly Target Schedule',
                'description' => 'Quarter-by-quarter target schedule derived from the current OPCR register.',
                'columns' => ['Success indicator', 'Q1 target', 'Q2 target', 'Q3 target', 'Q4 target', 'Remarks'],
                'editable' => false,
                'source_note' => 'Rows are read from the current OPCR target register. Use OPCR to maintain the source records.',
            ],
            'annex-h' => [
                'slug' => 'annex-h',
                'title' => 'Annex H — Cascading and Accountability',
                'description' => 'Workspace for cascading objectives, accountable units, and delivery commitments.',
                'columns' => ['Cascaded objective', 'Accountable unit', 'Deliverable', 'Measure', 'Target'],
                'editable' => true,
                'source_note' => 'The referenced legacy workbook is unavailable in the source archive; this current workspace preserves the destination and its expected structure.',
            ],
            'annex-j' => [
                'slug' => 'annex-j',
                'title' => 'Annex J — Monitoring and Evaluation',
                'description' => 'Workspace for review findings, monitoring evidence, and corrective actions.',
                'columns' => ['Review date', 'Objective / indicator', 'Finding', 'Action owner', 'Due date', 'Status'],
                'editable' => true,
                'source_note' => 'The referenced legacy workbook is unavailable in the source archive; this current workspace preserves the destination and its expected structure.',
            ],
            'annex-k' => [
                'slug' => 'annex-k',
                'title' => 'Annex K — Governance Review',
                'description' => 'Workspace for governance-review decisions, evidence, and next steps.',
                'columns' => ['Review date', 'Unit / committee', 'Evidence', 'Decision', 'Next step', 'Owner'],
                'editable' => true,
                'source_note' => 'The referenced legacy workbook is unavailable in the source archive; this current workspace preserves the destination and its expected structure.',
            ],
        ];
    }

    /**
     * @return array{slug: string, title: string, description: string, columns: list<string>, source_note: string, editable: bool}|null
     */
    public static function findAnnex(string $slug): ?array
    {
        return self::annexes()[$slug] ?? null;
    }
}
