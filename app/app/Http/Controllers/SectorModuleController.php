<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\User;
use App\Modules\SectorDetailRegistry;
use App\Modules\SectorModuleRegistry;
use App\Services\AuditLogService;
use App\Services\NotificationService;
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
        private readonly NotificationService $notifications,
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

        $details = SectorDetailRegistry::forPillar($module['slug']);
        $progressSummary = DB::table($module['progress_table'])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $pendingChanges = (($request->user()?->isAdmin() ?? false) || ($request->user()?->isFocal() ?? false))
            ? DB::table('progress_pending_changes')->where('module', $slug)->where('decision', 'Pending')->orderByDesc('submitted_at')->limit(25)->get()
            : collect();

        return Inertia::render('Sectors/Show', [
            'module' => $module,
            'rows' => $rows,
            'progress' => $progress,
            'schedule' => $schedule,
            'details' => $details,
            'progressSummary' => $progressSummary,
            'pendingChanges' => $pendingChanges,
            'canManage' => ($request->user()?->isAdmin() ?? false) || ($request->user()?->isFocal() ?? false),
        ]);
    }

    public function storeRow(Request $request, string $slug): RedirectResponse
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

        $user = $this->userOrFail($request);
        $data = [
            'category' => $request->string('category')->toString(),
            'year' => $request->integer('year'),
            'description' => $request->string('description')->toString(),
        ];

        if ($user->isAdmin() || $user->isFocal()) {
            DB::table($module['table'])->insert($data);

            return back()->with('success', 'Indicator added.');
        }

        DB::table('progress_pending_changes')->insert($data + [
            'module' => $slug,
            'change_type' => 'add_row',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'decision' => 'Pending',
        ]);

        $this->notifyReviewers($user, 'Indicator change submitted', "{$user->email} submitted a new {$module['label']} indicator for approval.");

        return back()->with('success', 'Indicator submitted for approval.');
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

        $data = [
            'category' => $request->string('category')->toString(),
            'year' => $request->integer('year'),
            'description' => $request->string('description')->toString(),
        ];

        DB::table($module['table'])
            ->where('id', $id)
            ->update([
                'category' => $data['category'],
                'year' => $data['year'],
                'description' => $data['description'],
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
            'status' => ['required', 'in:Not Accomplished/Started,Ongoing,Accomplished'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $user = $this->userOrFail($request);
        $progress = DB::table($module['progress_table'])->where('id', $id)->first();
        if ($progress === null) {
            abort(404);
        }

        DB::table($module['progress_table'])
            ->where('id', $id)
            ->update([
                'status' => $request->string('status')->toString(),
                'remarks' => $request->filled('remarks') ? $request->string('remarks')->toString() : null,
                'updated_by' => $user->id,
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

    public function destroyRow(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = SectorModuleRegistry::find($slug);
        if ($module === null) {
            abort(404);
        }
        abort_unless(($user = $this->userOrFail($request))->isAdmin() || $user->isFocal(), 403);
        abort_unless(DB::table($module['table'])->where('id', $id)->delete() > 0, 404);

        return back()->with('success', 'Indicator deleted.');
    }

    public function decidePending(Request $request, string $slug, int $change): RedirectResponse
    {
        $module = SectorModuleRegistry::find($slug);
        if ($module === null) {
            abort(404);
        }
        abort_unless(($user = $this->userOrFail($request))->isAdmin() || $user->isFocal(), 403);
        Validator::make($request->all(), ['decision' => ['required', 'in:Approved,Rejected']])->validate();

        $pending = DB::table('progress_pending_changes')->where('id', $change)->where('module', $slug)->where('decision', 'Pending')->first();
        if ($pending === null) {
            abort(404);
        }

        if ($request->string('decision')->toString() === 'Approved') {
            if ($pending->change_type === 'add_row') {
                DB::table($module['table'])->insert(['category' => $pending->category, 'year' => $pending->year, 'description' => $pending->description]);
            } else {
                DB::table($module['progress_table'])->updateOrInsert(
                    ['category' => $pending->category, 'year' => $pending->year, 'month' => $pending->month],
                    ['status' => $pending->status, 'remarks' => $pending->remarks, 'description' => $pending->description, 'updated_by' => $this->userId($request), 'updated_at' => now()],
                );
            }
        }

        DB::table('progress_pending_changes')->where('id', $change)->update(['decision' => $request->string('decision')->toString()]);

        $submittedBy = $this->idValue($pending->submitted_by);
        $decision = $request->string('decision')->toString();
        if ($submittedBy > 0 && $submittedBy !== $user->id) {
            $this->notifications->create(
                $submittedBy,
                $decision === 'Approved' ? NotificationType::Approved : NotificationType::Returned,
                'Roadmap change '.$decision,
                "Your {$module['label']} change was {$decision}.",
                $change,
                'progress_pending_changes',
            );
        }

        return back()->with('success', 'Pending roadmap change reviewed.');
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

    private function userOrFail(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function idValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function notifyReviewers(User $user, string $title, string $message): void
    {
        $this->notifications->createForRolesExcept(
            ['admin', 'focal'],
            $user->id,
            NotificationType::Edit,
            $title,
            $message,
            null,
            'progress_pending_changes',
        );
    }
}
