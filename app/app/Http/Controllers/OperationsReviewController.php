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

final class OperationsReviewController extends Controller
{
    /** @var list<string> */
    private const FIELDS = [
        'department', 'head_deputy', 'documenter', 'strategic_contributions', 'deliverable', 'deadline',
        'status', 'meeting_venue_schedule', 'scoreboard_location_oic', 'action_point', 'responsible_person',
        'target_date', 'action_status', 'wins_celebrated', 'best_performers_recognized', 'frequency', 'prepared_by', 'approved_by',
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);
        $scope = $user->isAdmin() || $user->isFocal() ? 'all' : "user:{$user->id}";

        $reviews = CacheInvalidationService::remember('ops_review', "index:{$scope}", function () use ($user): array {
            return DB::table('operations_review as o')
                ->join('users as u', 'u.id', '=', 'o.employee_id')
                ->when(! $user->isAdmin() && ! $user->isFocal(), fn ($query) => $query->where('o.employee_id', $user->id))
                ->orderByDesc('o.created_at')
                ->get(['o.id', 'o.employee_id', 'o.form_data', 'o.pdf_file', 'o.created_at', 'u.email as employee_email'])
                ->map(fn (\stdClass $review): array => [
                    'id' => $this->idValue($review->id),
                    'employee_id' => $this->idValue($review->employee_id),
                    'employee_email' => is_scalar($review->employee_email) ? (string) $review->employee_email : '',
                    'data' => $this->decodeFormData($review->form_data),
                    'pdf_file' => $review->pdf_file,
                    'created_at' => $review->created_at,
                ])->values()->all();
        }, 60);

        return Inertia::render('OperationsReview/Index', [
            'reviews' => $reviews,
            'fields' => self::FIELDS,
            'uploadUrl' => '/operations-review/upload',
            'userId' => $user->id,
            'canEditAny' => $user->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $data = $this->validatedData($request);
        $id = DB::table('operations_review')->insertGetId([
            'employee_id' => $user->id,
            'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
        $this->audit->record($user->id, 'operations_review.form_created', 'operations_review', (string) $id, request: $request);

        $this->notifications->createForRolesExcept(
            ['admin', 'focal'],
            $user->id,
            NotificationType::Upload,
            'Operations Review submitted',
            $user->email.' saved an Operations Review for review.',
            $id,
            'operations_review',
        );

        CacheInvalidationService::onOpsReviewChange();

        return back()->with('success', 'Operations Review saved.');
    }

    public function update(Request $request, int $review): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $existing = DB::table('operations_review')->where('id', $review)->first();
        abort_if($existing === null, 404);
        abort_unless($user->isAdmin() || $this->idValue($existing->employee_id) === $user->id, 403);
        $data = $this->validatedData($request);

        DB::table('operations_review')->where('id', $review)->update([
            'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        $this->audit->record($user->id, 'operations_review.form_updated', 'operations_review', (string) $review, request: $request);

        CacheInvalidationService::onOpsReviewChange();

        return back()->with('success', 'Operations Review updated.');
    }

    public function destroy(Request $request, int $review): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $existing = DB::table('operations_review')->where('id', $review)->first();
        abort_if($existing === null, 404);
        abort_unless($user->isAdmin() || $this->idValue($existing->employee_id) === $user->id, 403);
        DB::table('operations_review')->where('id', $review)->delete();
        $this->audit->record($user->id, 'operations_review.form_deleted', 'operations_review', (string) $review, request: $request);

        CacheInvalidationService::onOpsReviewChange();

        return back()->with('success', 'Operations Review deleted.');
    }

    /** @return array<string, string> */
    private function validatedData(Request $request): array
    {
        $rules = [];
        foreach (self::FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:5000'];
        }
        $rules['department'] = ['required', 'string', 'max:255'];
        $rules['head_deputy'] = ['required', 'string', 'max:255'];
        $rules['documenter'] = ['required', 'string', 'max:255'];
        Validator::make($request->all(), $rules)->validate();
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = $request->string($field)->toString();
        }
        $data['department'] = $request->string('department')->toString();
        $data['head_deputy'] = $request->string('head_deputy')->toString();
        $data['documenter'] = $request->string('documenter')->toString();

        return $data;
    }

    private function idValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return array<string, string> */
    private function decodeFormData(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $data = [];
        foreach ($decoded as $key => $value) {
            $data[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    /** @throws AuthenticationException */
    private function userOrFail(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
