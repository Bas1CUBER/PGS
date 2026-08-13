<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class CommPlanController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $rows = DB::table('communication_plan_roadmap')
            ->orderBy('id')
            ->get();

        return Inertia::render('CommPlan/Index', [
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'objective' => ['required', 'string', 'max:5000'],
            'target_audience' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'channel' => ['nullable', 'string', 'max:255'],
            'timeframe' => ['nullable', 'string', 'max:255'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $id = DB::table('communication_plan_roadmap')->insertGetId([
            'objective' => $request->string('objective')->toString(),
            'target_audience' => $this->nullableText($request, 'target_audience'),
            'message' => $this->nullableText($request, 'message'),
            'channel' => $this->nullableText($request, 'channel'),
            'timeframe' => $this->nullableText($request, 'timeframe'),
            'requirements' => $this->nullableText($request, 'requirements'),
            'responsible_person' => $this->nullableText($request, 'responsible_person'),
            'created_by' => $this->userId($request),
            'status' => 'Not Accomplished/Started',
        ]);

        $this->audit->record(
            $this->userId($request),
            'commplan.row_created',
            'communication_plan_roadmap',
            (string) $id,
            request: $request,
        );

        return back()->with('success', 'Communication plan row added.');
    }

    public function update(Request $request, int $row): RedirectResponse
    {
        Validator::make($request->all(), [
            'objective' => ['required', 'string', 'max:5000'],
            'target_audience' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'channel' => ['nullable', 'string', 'max:255'],
            'timeframe' => ['nullable', 'string', 'max:255'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:Not Accomplished/Started,Ongoing,Completed'],
        ])->validate();

        DB::table('communication_plan_roadmap')->where('id', $row)->update([
            'objective' => $request->string('objective')->toString(),
            'target_audience' => $this->nullableText($request, 'target_audience'),
            'message' => $this->nullableText($request, 'message'),
            'channel' => $this->nullableText($request, 'channel'),
            'timeframe' => $this->nullableText($request, 'timeframe'),
            'requirements' => $this->nullableText($request, 'requirements'),
            'responsible_person' => $this->nullableText($request, 'responsible_person'),
            'status' => $request->filled('status') ? $request->string('status')->toString() : DB::raw('status'),
        ]);

        $this->audit->record(
            $this->userId($request),
            'commplan.row_updated',
            'communication_plan_roadmap',
            (string) $row,
            request: $request,
        );

        return back()->with('success', 'Communication plan row updated.');
    }

    public function destroy(Request $request, int $row): RedirectResponse
    {
        DB::table('communication_plan_roadmap')->where('id', $row)->delete();

        $this->audit->record(
            $this->userId($request),
            'commplan.row_deleted',
            'communication_plan_roadmap',
            (string) $row,
            request: $request,
        );

        return back()->with('success', 'Communication plan row deleted.');
    }

    private function nullableText(Request $request, string $key): ?string
    {
        return $request->filled($key) ? $request->string($key)->toString() : null;
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
