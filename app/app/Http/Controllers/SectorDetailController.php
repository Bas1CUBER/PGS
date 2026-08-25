<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\SectorDetailRegistry;
use App\Modules\SectorModuleRegistry;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use App\Support\CsvFormulaGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders and edits the sector detail wide tables from SectorDetailRegistry.
 * Year columns are edited as plain text cells (numeric values stay strings
 * in the legacy schema). Routes are nested under the pillar:
 * `/sectors/{pillar}/{slug}`.
 */
final class SectorDetailController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function show(Request $request, string $pillar, string $slug): Response
    {
        $module = SectorDetailRegistry::find($pillar, $slug);

        if ($module === null) {
            abort(404);
        }

        $pillarModule = SectorModuleRegistry::find($pillar);

        if ($pillarModule === null) {
            abort(404);
        }

        $columns = array_merge($module['columns'], $module['year_columns']);

        $lockColumn = $this->lockColumn($module['table']);

        // paginate() reads ?page= from the current request, so the page must
        // be part of the cache key or one page's result is served to all.
        $page = (int) $request->query('page', '1');

        [$rows, $stats] = CacheInvalidationService::remember(
            'sector_detail',
            "{$pillar}:{$slug}:p{$page}",
            function () use ($module, $columns, $lockColumn, $slug): array {
                $rows = DB::table($module['table'])
                    ->orderBy('id')
                    ->paginate(50)
                    ->withQueryString();
                $rows->setCollection($rows->getCollection()->map(function (\stdClass $row) use ($columns, $lockColumn): array {
                    $rowArray = (array) $row;
                    $data = ['id' => is_numeric($rowArray['id'] ?? null) ? (int) $rowArray['id'] : 0];
                    foreach ($columns as $column) {
                        $data[$column] = $rowArray[$column] ?? null;
                    }
                    if ($lockColumn !== null) {
                        $data['locked'] = (bool) ($rowArray[$lockColumn] ?? false);
                    }

                    return $data;
                }));

                $stats = null;
                if ($slug === 'research-outputs') {
                    $stats = [
                        'ongoing' => DB::table($module['table'])
                            ->whereIn('phase_status', ['Planning', 'Data Gathering', 'Analyzing', 'Writing'])
                            ->count(),
                        'completed' => DB::table($module['table'])->where('outcome_status', 'Submitted')->count(),
                        'presented' => DB::table($module['table'])->where('outcome_status', 'Presented')->count(),
                        'published' => DB::table($module['table'])->where('outcome_status', 'Published')->count(),
                    ];
                }

                return [$rows, $stats];
            },
            60,
        );

        return Inertia::render('SectorDetails/Show', [
            'module' => $module + [
                'pillar_label' => $pillarModule['label'],
                'logo' => SectorDetailRegistry::logoFor($pillar, $slug),
            ],
            'columns' => $columns,
            'rows' => $rows,
            'stats' => $stats,
            'lockColumn' => $lockColumn,
            'canManage' => $this->canManage($request),
            'breadcrumbs' => [
                ['label' => 'Roadmaps', 'href' => '/sectors'],
                ['label' => $pillarModule['label'], 'href' => "/sectors/{$pillar}"],
                ['label' => $module['label']],
            ],
        ]);
    }

    public function update(Request $request, string $pillar, string $slug, int $id): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $module = SectorDetailRegistry::find($pillar, $slug);

        if ($module === null) {
            abort(404);
        }

        $editable = array_values(array_unique(array_merge($module['editable'], $module['year_columns'])));
        $rules = [];
        foreach ($editable as $column) {
            $rules[$column] = ['nullable', 'string', 'max:255'];
        }

        Validator::make($request->all(), $rules)->validate();

        $existing = DB::table($module['table'])->where('id', $id)->first();
        abort_if($existing === null, 404);

        // A locked row freezes edits for focals; admins keep break-glass
        // access so a mistaken lock can always be undone via toggleLock.
        $user = $request->user();
        if ($this->isLocked($existing, $module['table'])) {
            abort_unless($user !== null && $user->isAdmin(), 403, 'This roadmap row is locked.');
        }

        /** @var array<string, mixed> $existingArr */
        $existingArr = (array) $existing;
        $before = [];
        foreach ($editable as $column) {
            if (array_key_exists($column, $existingArr)) {
                $before[$column] = $existingArr[$column];
            }
        }

        $data = [];
        foreach ($editable as $column) {
            $data[$column] = $request->filled($column) ? $request->string($column)->toString() : null;
        }

        DB::table($module['table'])->where('id', $id)->update($data);

        $this->audit->record(
            $this->userId($request),
            "sector_detail.{$pillar}.{$slug}.row_updated",
            $module['table'],
            (string) $id,
            before: $before,
            after: $data,
            request: $request,
        );

        CacheInvalidationService::onSectorChange();

        return back()->with('success', 'Row updated.');
    }

    public function store(Request $request, string $pillar, string $slug): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $module = SectorDetailRegistry::find($pillar, $slug);
        abort_if($module === null, 404);

        $data = $this->validatedData($request, array_values(array_unique(array_merge($module['columns'], $module['year_columns']))));
        if (array_key_exists('is_head', $data) && $data['is_head'] === null) {
            $data['is_head'] = '0';
        }
        if (Schema::hasColumn($module['table'], 'created_by')) {
            $data['created_by'] = (string) $this->userId($request);
        }
        $id = DB::table($module['table'])->insertGetId($data);
        $this->audit->record($this->userId($request), "sector_detail.{$pillar}.{$slug}.row_created", $module['table'], (string) $id, request: $request);

        CacheInvalidationService::onSectorChange();

        return back()->with('success', 'Roadmap row added.');
    }

    public function destroy(Request $request, string $pillar, string $slug, int $id): RedirectResponse
    {
        $module = SectorDetailRegistry::find($pillar, $slug);
        abort_if($module === null, 404);
        abort_unless($this->canManage($request), 403);

        $existing = DB::table($module['table'])->where('id', $id)->first();
        abort_if($existing === null, 404);
        if ($this->isLocked($existing, $module['table'])) {
            $user = $request->user();
            abort_unless($user !== null && $user->isAdmin(), 403, 'This roadmap row is locked.');
        }

        abort_unless(DB::table($module['table'])->where('id', $id)->delete() > 0, 404);
        $this->audit->record($this->userId($request), "sector_detail.{$pillar}.{$slug}.row_deleted", $module['table'], (string) $id, request: $request);

        CacheInvalidationService::onSectorChange();

        return back()->with('success', 'Roadmap row deleted.');
    }

    public function toggleLock(Request $request, string $pillar, string $slug, int $id): RedirectResponse
    {
        $module = SectorDetailRegistry::find($pillar, $slug);
        abort_if($module === null, 404);
        abort_unless($this->canManage($request), 403);
        $lockColumn = $this->lockColumn($module['table']);
        abort_if($lockColumn === null, 422, 'This roadmap does not support row locking.');
        $row = DB::table($module['table'])->where('id', $id)->first();
        abort_if($row === null, 404);
        $rowArray = (array) $row;
        DB::table($module['table'])->where('id', $id)->update([$lockColumn => ! (bool) ($rowArray[$lockColumn] ?? false)]);
        $this->audit->record($this->userId($request), "sector_detail.{$pillar}.{$slug}.row_lock_toggled", $module['table'], (string) $id, request: $request);

        CacheInvalidationService::onSectorChange();

        return back()->with('success', 'Roadmap row lock updated.');
    }

    public function export(Request $request, string $pillar, string $slug): \Illuminate\Http\Response
    {
        $module = SectorDetailRegistry::find($pillar, $slug);
        abort_if($module === null, 404);
        $columns = array_merge($module['columns'], $module['year_columns']);
        $rows = DB::table($module['table'])->orderBy('id')->get();
        $handle = fopen('php://temp', 'r+');
        abort_if($handle === false, 500);
        fputcsv($handle, array_map(fn (string $column): string => str($column)->replace('_', ' ')->title()->toString(), $columns));
        foreach ($rows as $row) {
            $rowArray = (array) $row;
            fputcsv($handle, CsvFormulaGuard::row(array_map(fn (string $column): mixed => $rowArray[$column] ?? null, $columns)));
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        $contents = $contents === false ? '' : $contents;
        fclose($handle);

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.str($module['slug'])->slug('_').'.csv"',
        ]);
    }

    /** @param list<string> $fields
     *  @return array<string, string|null> */
    private function validatedData(Request $request, array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = ['nullable', 'string', 'max:5000'];
        }
        Validator::make($request->all(), $rules)->validate();
        $data = [];
        foreach ($fields as $field) {
            $value = $request->input($field);
            $data[$field] = is_string($value) && $value !== '' ? $value : null;
        }

        return $data;
    }

    /** @var array<string, string|null> */
    private static array $lockColumnCache = [];

    private function lockColumn(string $table): ?string
    {
        if (array_key_exists($table, self::$lockColumnCache)) {
            return self::$lockColumnCache[$table];
        }

        foreach (['row_locked', 'locked', 'is_locked'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                self::$lockColumnCache[$table] = $column;

                return $column;
            }
        }

        self::$lockColumnCache[$table] = null;

        return null;
    }

    private function isLocked(object $row, string $table): bool
    {
        $column = $this->lockColumn($table);

        if ($column === null) {
            return false;
        }

        return (bool) (((array) $row)[$column] ?? false);
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->isAdmin() || $user->isFocal());
    }

    /**
     * @throws AuthenticationException
     */
    private function userId(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return $user->id;
    }
}
