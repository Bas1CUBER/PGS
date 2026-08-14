<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\LegacyFormRegistry;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class LegacyFormController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function annex(Request $request, string $slug): InertiaResponse
    {
        $definition = LegacyFormRegistry::findAnnex($slug);
        abort_if($definition === null, 404);

        return Inertia::render('LegacyForms/Show', [
            'form' => $definition,
            'rows' => $this->annexViewRows($definition),
            'downloadUrl' => "/annex/{$slug}/download",
            'canManage' => ($request->user()?->isAdmin() ?? false) || ($request->user()?->isFocal() ?? false),
        ]);
    }

    public function annexStore(Request $request, string $slug): RedirectResponse
    {
        $definition = LegacyFormRegistry::findAnnex($slug);
        abort_if($definition === null, 404);
        $this->assertEditable($request, $definition);
        $values = $this->validatedAnnexValues($request, $definition['columns']);
        $id = DB::table('annex_workspace_rows')->insertGetId([
            'slug' => $slug,
            'data' => json_encode($values, JSON_UNESCAPED_UNICODE),
            'created_by' => $this->userId($request),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->record($this->userId($request), 'annex.row_created', 'annex_workspace_rows', (string) $id, request: $request);

        return back()->with('success', 'Annex row added.');
    }

    public function annexUpdate(Request $request, string $slug, int $id): RedirectResponse
    {
        $definition = LegacyFormRegistry::findAnnex($slug);
        abort_if($definition === null, 404);
        $this->assertEditable($request, $definition);
        $values = $this->validatedAnnexValues($request, $definition['columns']);
        abort_unless(DB::table('annex_workspace_rows')->where('id', $id)->where('slug', $slug)->update([
            'data' => json_encode($values, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]) > 0, 404);
        $this->audit->record($this->userId($request), 'annex.row_updated', 'annex_workspace_rows', (string) $id, request: $request);

        return back()->with('success', 'Annex row updated.');
    }

    public function annexDestroy(Request $request, string $slug, int $id): RedirectResponse
    {
        $definition = LegacyFormRegistry::findAnnex($slug);
        abort_if($definition === null, 404);
        $this->assertEditable($request, $definition);
        abort_unless(DB::table('annex_workspace_rows')->where('id', $id)->where('slug', $slug)->delete() > 0, 404);
        $this->audit->record($this->userId($request), 'annex.row_deleted', 'annex_workspace_rows', (string) $id, request: $request);

        return back()->with('success', 'Annex row deleted.');
    }

    public function annexDownload(Request $request, string $slug): Response
    {
        $definition = LegacyFormRegistry::findAnnex($slug);
        abort_if($definition === null, 404);

        $rows = $this->annexRows($slug);

        return $this->csvResponse($definition['title'], $definition['columns'], $rows);
    }

    public function opcr(Request $request): InertiaResponse
    {
        $rows = DB::table('performance_targets')->orderBy('id')->get()->map(fn (\stdClass $row): array => [
            'id' => $this->rowId($row),
            'strategic_goal' => $this->nullableString($row->strategic_goal),
            'success_indicator' => $this->string($row->success_indicator),
            'division_accountable' => $this->string($row->division_accountable),
            'annual_target' => $this->nullableString($row->annual_target),
            'quarter1_target' => $this->nullableString($row->quarter1_target),
            'quarter2_target' => $this->nullableString($row->quarter2_target),
            'quarter3_target' => $this->nullableString($row->quarter3_target),
            'quarter4_target' => $this->nullableString($row->quarter4_target),
            'remarks' => $this->nullableString($row->remarks),
        ])->values()->all();

        return Inertia::render('LegacyForms/Opcr', [
            'rows' => $rows,
            'exportUrl' => '/opcr/export',
        ]);
    }

    public function opcrStore(Request $request): RedirectResponse
    {
        $data = $this->validatedOpcr($request);
        $id = DB::table('performance_targets')->insertGetId($data);
        $this->audit->record($this->userId($request), 'opcr.row_created', 'performance_targets', (string) $id, request: $request);

        return back()->with('success', 'OPCR target added.');
    }

    public function opcrUpdate(Request $request, int $id): RedirectResponse
    {
        $data = $this->validatedOpcr($request);
        abort_unless(DB::table('performance_targets')->where('id', $id)->update($data) > 0, 404);
        $this->audit->record($this->userId($request), 'opcr.row_updated', 'performance_targets', (string) $id, request: $request);

        return back()->with('success', 'OPCR target updated.');
    }

    public function opcrDestroy(Request $request, int $id): RedirectResponse
    {
        abort_unless(DB::table('performance_targets')->where('id', $id)->delete() > 0, 404);
        $this->audit->record($this->userId($request), 'opcr.row_deleted', 'performance_targets', (string) $id, request: $request);

        return back()->with('success', 'OPCR target removed.');
    }

    public function opcrDownload(Request $request): Response
    {
        $rows = array_values(DB::table('performance_targets')->orderBy('id')->get()->map(fn (\stdClass $row): array => [
            $this->nullableString($row->strategic_goal),
            $this->string($row->success_indicator),
            $this->string($row->division_accountable),
            $this->nullableString($row->annual_target),
            $this->nullableString($row->quarter1_target),
            $this->nullableString($row->quarter2_target),
            $this->nullableString($row->quarter3_target),
            $this->nullableString($row->quarter4_target),
            $this->nullableString($row->remarks),
        ])->all());

        return $this->csvResponse('OPCR', [
            'Strategic goal', 'Success indicator', 'Division accountable', 'Annual target',
            'Q1 target', 'Q2 target', 'Q3 target', 'Q4 target', 'Remarks',
        ], $rows);
    }

    /** @return list<list<string|null>> */
    private function annexRows(string $slug): array
    {
        $definition = LegacyFormRegistry::findAnnex($slug);
        if ($definition !== null && $definition['editable']) {
            return array_values(DB::table('annex_workspace_rows')->where('slug', $slug)->orderBy('id')->get()->map(function (\stdClass $row) use ($definition): array {
                $raw = $row->data;
                $data = is_string($raw) ? json_decode($raw, true) : null;
                $data = is_array($data) ? $data : [];

                return array_map(fn (string $column): ?string => isset($data[$column]) && is_scalar($data[$column]) ? (string) $data[$column] : null, $definition['columns']);
            })->all());
        }

        $targets = DB::table('performance_targets')->orderBy('id')->get();

        return match ($slug) {
            'annex-d' => array_values($targets->map(fn (\stdClass $row): array => [
                $this->nullableString($row->strategic_goal), $this->string($row->success_indicator), $this->string($row->division_accountable), $this->nullableString($row->annual_target),
            ])->all()),
            'annex-e' => array_values($targets->map(fn (\stdClass $row): array => [
                $this->string($row->success_indicator), $this->nullableString($row->quarter1_target), $this->nullableString($row->quarter2_target),
                $this->nullableString($row->quarter3_target), $this->nullableString($row->quarter4_target), $this->nullableString($row->remarks),
            ])->all()),
            default => [],
        };
    }

    /**
     * @param  array{slug: string, title: string, description: string, columns: list<string>, source_note: string, editable: bool}  $definition
     * @return list<array{id: int, values: array<int, string|null>}>|list<list<string|null>>
     */
    private function annexViewRows(array $definition): array
    {
        if (! $definition['editable']) {
            return $this->annexRows($definition['slug']);
        }

        return array_values(DB::table('annex_workspace_rows')->where('slug', $definition['slug'])->orderBy('id')->get()->map(function (\stdClass $row) use ($definition): array {
            $raw = $row->data;
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $data = is_array($data) ? $data : [];

            return [
                'id' => $this->rowId($row),
                'values' => array_map(fn (string $column): ?string => isset($data[$column]) && is_scalar($data[$column]) ? (string) $data[$column] : null, $definition['columns']),
            ];
        })->all());
    }

    /** @param list<string> $columns
     *  @return array<string, string|null> */
    private function validatedAnnexValues(Request $request, array $columns): array
    {
        Validator::make($request->all(), [
            'values' => ['required', 'array', 'size:'.count($columns)],
            'values.*' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $values = $request->input('values', []);
        $result = [];
        foreach ($columns as $index => $column) {
            $value = $values[$index] ?? null;
            $result[$column] = $value === null || $value === '' ? null : (string) $value;
        }

        return $result;
    }

    /** @param array{editable: bool} $definition */
    private function assertEditable(Request $request, array $definition): void
    {
        abort_unless($definition['editable'], 422, 'This Annex is derived from the maintained register.');
        abort_unless(($request->user()?->isAdmin() ?? false) || ($request->user()?->isFocal() ?? false), 403);
    }

    /** @return array<string, string|null> */
    private function validatedOpcr(Request $request): array
    {
        Validator::make($request->all(), [
            'strategic_goal' => ['nullable', 'string', 'max:5000'],
            'success_indicator' => ['required', 'string', 'max:5000'],
            'division_accountable' => ['required', 'string', 'max:255'],
            'annual_target' => ['nullable', 'string', 'max:255'],
            'quarter1_target' => ['nullable', 'string', 'max:255'],
            'quarter2_target' => ['nullable', 'string', 'max:255'],
            'quarter3_target' => ['nullable', 'string', 'max:255'],
            'quarter4_target' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return [
            'strategic_goal' => $request->filled('strategic_goal') ? $request->string('strategic_goal')->toString() : null,
            'success_indicator' => $request->string('success_indicator')->toString(),
            'division_accountable' => $request->string('division_accountable')->toString(),
            'annual_target' => $request->filled('annual_target') ? $request->string('annual_target')->toString() : null,
            'quarter1_target' => $request->filled('quarter1_target') ? $request->string('quarter1_target')->toString() : null,
            'quarter2_target' => $request->filled('quarter2_target') ? $request->string('quarter2_target')->toString() : null,
            'quarter3_target' => $request->filled('quarter3_target') ? $request->string('quarter3_target')->toString() : null,
            'quarter4_target' => $request->filled('quarter4_target') ? $request->string('quarter4_target')->toString() : null,
            'remarks' => $request->filled('remarks') ? $request->string('remarks')->toString() : null,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|null>>  $rows
     */
    private function csvResponse(string $name, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');
        abort_if($handle === false, 500);
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        $contents = $contents === false ? '' : $contents;
        fclose($handle);

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.str($name)->slug('_').'.csv"',
        ]);
    }

    private function rowId(\stdClass $row): int
    {
        return is_numeric($row->id) ? (int) $row->id : 0;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : $this->string($value);
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
