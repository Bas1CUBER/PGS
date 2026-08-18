<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use App\Services\NotificationService;
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
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $rows = CacheInvalidationService::remember('comm_plan', 'index', function (): array {
            return DB::table('communication_plan_roadmap')
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->values()
                ->all();
        }, 60);

        return Inertia::render('CommPlan/Index', [
            'rows' => $rows,
            'userId' => $user?->id,
            'canManage' => $user !== null && ($user->isAdmin() || $user->isFocal()),
            'uploadUrl' => '/communication-plan/upload',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->userOrFail($request);
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
            'created_by' => $user->id,
            'status' => 'Not Accomplished/Started',
        ]);

        $this->audit->record(
            $this->userId($request),
            'commplan.row_created',
            'communication_plan_roadmap',
            (string) $id,
            request: $request,
        );

        $this->notifications->createForRolesExcept(
            ['admin', 'focal'],
            $user->id,
            NotificationType::Edit,
            'Communication plan updated',
            $user->email.' added a communication plan item for review.',
            $id,
            'communication_plan_roadmap',
        );

        CacheInvalidationService::onCommPlanChange();

        return back()->with('success', 'Communication plan row added.');
    }

    public function update(Request $request, int $row): RedirectResponse
    {
        $user = $this->userOrFail($request);
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

        $existing = DB::table('communication_plan_roadmap')->where('id', $row)->first();

        if ($existing === null) {
            abort(404);
        }

        abort_unless($user->isAdmin() || $user->isFocal() || $this->idValue($existing->created_by) === $user->id, 403);

        DB::table('communication_plan_roadmap')->where('id', $row)->update([
            'objective' => $request->string('objective')->toString(),
            'target_audience' => $this->nullableText($request, 'target_audience'),
            'message' => $this->nullableText($request, 'message'),
            'channel' => $this->nullableText($request, 'channel'),
            'timeframe' => $this->nullableText($request, 'timeframe'),
            'requirements' => $this->nullableText($request, 'requirements'),
            'responsible_person' => $this->nullableText($request, 'responsible_person'),
            'status' => $request->filled('status') ? $request->string('status')->toString() : $existing->status,
        ]);

        $this->audit->record(
            $user->id,
            'commplan.row_updated',
            'communication_plan_roadmap',
            (string) $row,
            request: $request,
        );

        CacheInvalidationService::onCommPlanChange();

        return back()->with('success', 'Communication plan row updated.');
    }

    public function destroy(Request $request, int $row): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $existing = DB::table('communication_plan_roadmap')->where('id', $row)->first();
        abort_if($existing === null, 404);
        abort_unless($user->isAdmin() || $user->isFocal() || $this->idValue($existing->created_by) === $user->id, 403);
        DB::table('communication_plan_roadmap')->where('id', $row)->delete();

        $this->audit->record(
            $user->id,
            'commplan.row_deleted',
            'communication_plan_roadmap',
            (string) $row,
            request: $request,
        );

        CacheInvalidationService::onCommPlanChange();

        return back()->with('success', 'Communication plan row deleted.');
    }

    private function nullableText(Request $request, string $key): ?string
    {
        return $request->filled($key) ? $request->string($key)->toString() : null;
    }

    private function idValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function userOrFail(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
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
