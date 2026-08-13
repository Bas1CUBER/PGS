<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\SectorDetailRegistry;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders and edits the sector detail wide tables from SectorDetailRegistry.
 * Year columns are edited as plain text cells (numeric values stay strings
 * in the legacy schema).
 */
final class SectorDetailController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $module = SectorDetailRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $columns = array_merge($module['columns'], $module['year_columns']);

        $rows = DB::table($module['table'])
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('SectorDetails/Show', [
            'module' => $module,
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }

    public function update(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = SectorDetailRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $editable = array_merge($module['editable'], $module['year_columns']);
        $rules = [];
        foreach ($editable as $column) {
            $rules[$column] = ['nullable', 'string', 'max:255'];
        }

        Validator::make($request->all(), $rules)->validate();

        $data = [];
        foreach ($editable as $column) {
            $data[$column] = $request->filled($column) ? $request->string($column)->toString() : null;
        }

        DB::table($module['table'])->where('id', $id)->update($data);

        $this->audit->record(
            $this->userId($request),
            "sector_detail.{$slug}.row_updated",
            $module['table'],
            (string) $id,
            request: $request,
        );

        return back()->with('success', 'Row updated.');
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
