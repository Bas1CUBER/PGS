<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\SectorModuleRegistry;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One controller renders every sector pillar from SectorModuleRegistry.
 */
final class SectorModuleController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $modules = SectorModuleRegistry::modules();

        return Inertia::render('Sectors/Index', [
            'modules' => $modules,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $module = SectorModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $rows = DB::table($module['table'])
            ->orderByDesc('year')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $progress = DB::table($module['progress_table'])
            ->orderByDesc('year')
            ->orderBy('month')
            ->limit(50)
            ->get();

        $schedule = $module['schedule_table'] !== null
            ? DB::table($module['schedule_table'])
                ->orderByDesc('year')
                ->orderBy('month')
                ->limit(50)
                ->get()
            : null;

        return Inertia::render('Sectors/Show', [
            'module' => $module,
            'rows' => $rows,
            'progress' => $progress,
            'schedule' => $schedule,
        ]);
    }

    public function updateRow(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = SectorModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        Validator::make($request->all(), [
            'category' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'description' => ['required', 'string', 'max:5000'],
        ])->validate();

        DB::table($module['table'])
            ->where('id', $id)
            ->update([
                'category' => $request->string('category')->toString(),
                'year' => $request->integer('year'),
                'description' => $request->string('description')->toString(),
            ]);

        $this->audit->record(
            $this->userId($request),
            "sector.{$slug}.row_updated",
            $module['table'],
            (string) $id,
            request: $request,
        );

        return back()->with('success', 'Indicator updated.');
    }

    public function updateProgress(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = SectorModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        Validator::make($request->all(), [
            'status' => ['required', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::table($module['progress_table'])
            ->where('id', $id)
            ->update([
                'status' => $request->string('status')->toString(),
                'remarks' => $request->filled('remarks') ? $request->string('remarks')->toString() : null,
                'updated_by' => $this->userId($request),
            ]);

        $this->audit->record(
            $this->userId($request),
            "sector.{$slug}.progress_updated",
            $module['progress_table'],
            (string) $id,
            request: $request,
        );

        return back()->with('success', 'Progress updated.');
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
