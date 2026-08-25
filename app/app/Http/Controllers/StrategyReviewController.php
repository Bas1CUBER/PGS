<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\StrategyReviewForm;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use App\Services\NotificationService;
use App\Services\TransitionsWorkflowService;
use App\Services\WorkflowRegistry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class StrategyReviewController extends Controller
{
    /** @var list<string> */
    private const FIELDS = [
        'review_date', 'objective', 'directly_contributing_units', 'measure', 'target',
        'actual_to_date_measure', 'status_measure', 'kra1_key_results_area', 'kra1_deliverable',
        'kra1_actual_to_date', 'kra1_status', 'kra2_key_results_area', 'kra2_deliverable',
        'kra2_actual_to_date', 'kra2_status', 'kra3_key_results_area', 'kra3_deliverable',
        'kra3_actual_to_date', 'kra3_status', 'continue', 'stop', 'start', 'prepared_by', 'approved_by',
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** @return TransitionsWorkflowService<StrategyReviewForm> */
    private function workflows(): TransitionsWorkflowService
    {
        return WorkflowRegistry::strategyReviewForms();
    }

    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);
        $scope = $user->isEmployee() ? "user:{$user->id}" : 'all';

        $forms = CacheInvalidationService::remember('strat_review', "index:{$scope}", function () use ($user): array {
            return StrategyReviewForm::query()
                ->with('employee:id,email')
                ->when($user->isEmployee(), fn ($query) => $query->where('employee_id', $user->id))
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (StrategyReviewForm $form): array => [
                    'id' => $form->id,
                    'employee_id' => $form->employee_id,
                    'employee_email' => $form->employee !== null ? $form->employee->email : '',
                    'data' => $this->decodeFormData($form->form_data),
                    'pdf_filename' => $form->pdf_filename,
                    'status' => $form->status,
                    'created_at' => $form->created_at,
                    'updated_at' => $form->updated_at,
                ])->values()->all();
        }, 60);

        return Inertia::render('StrategyReview/Index', [
            'forms' => $forms,
            'canReview' => $user->isAdmin() || $user->isFocal(),
            'userId' => $user->id,
            'canEditAny' => $user->isAdmin(),
            'fields' => self::FIELDS,
            'uploadUrl' => '/strategy-review/upload',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $data = $this->validatedData($request);
        $status = $request->string('status')->toString() === 'Submitted' ? 'Submitted' : 'Draft';

        $row = StrategyReviewForm::query()->create([
            'employee_id' => $user->id,
            'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => $status,
        ]);
        $id = $row->id;

        $this->audit->record($user->id, 'strategy_review.form_created', 'strategy_review_forms', (string) $id, request: $request);

        if ($status === 'Submitted') {
            $this->notifications->createForRolesExcept(
                ['admin', 'focal'],
                $user->id,
                NotificationType::Upload,
                'Strategy Review submitted',
                $user->email.' submitted a Strategy Review for approval.',
                $id,
                'strategy_review_forms',
            );
        }

        CacheInvalidationService::onStratReviewChange();

        return back()->with('success', $status === 'Submitted' ? 'Strategy Review submitted.' : 'Strategy Review draft saved.');
    }

    public function update(Request $request, int $form): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $existing = StrategyReviewForm::query()->find($form);
        abort_unless($existing !== null && ($user->isAdmin() || $existing->employee_id === $user->id), 403);
        /** @var StrategyReviewForm $existing */
        $data = $this->validatedData($request);
        $status = $request->string('status')->toString() === 'Submitted' ? 'Submitted' : 'Draft';

        // Reject a forbidden status move before persisting anything, then
        // save the content edit; any actual transition goes through the
        // workflow engine (re-locked row, atomic audit record). Both writes
        // share ONE transaction so a precondition failure cannot leave the
        // content changed while the status stays stale.
        $transitionNeeded = $status !== $existing->getRawOriginal('status');
        if ($transitionNeeded && ! $this->workflows()->canTransition($existing, $status, $user)) {
            abort(403);
        }

        DB::transaction(function () use ($request, $existing, $status, $user, $data, $transitionNeeded, $form): void {
            $existing->update([
                'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);

            if ($transitionNeeded) {
                // Nested DB::transaction becomes a savepoint: a failure here
                // rolls back the content save too.
                $this->workflows()->transition($existing, $status, $user);
            } else {
                $this->audit->record(
                    $user->id,
                    'strategy_review.form_updated',
                    'strategy_review_forms',
                    (string) $form,
                    request: $request,
                );
            }
        });

        CacheInvalidationService::onStratReviewChange();

        return back()->with('success', $status === 'Submitted' ? 'Strategy Review submitted.' : 'Strategy Review draft saved.');
    }

    public function review(Request $request, int $form): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);
        Validator::make($request->all(), ['status' => ['required', 'in:Approved,Returned']])->validate();
        $existing = StrategyReviewForm::query()->find($form);
        abort_if($existing === null, 404);
        abort_unless($existing->employee_id !== $user->id, 403, 'A reviewer cannot approve their own form.');

        $status = $request->string('status')->toString();

        // Authorization, preconditions and the audit trail are enforced
        // atomically by the workflow engine.
        $this->workflows()->transition($existing, $status, $user);

        $ownerId = $existing->employee_id;
        if ($ownerId > 0) {
            $this->notifications->create(
                $ownerId,
                $status === 'Approved' ? NotificationType::Approved : NotificationType::Returned,
                'Strategy Review '.$status,
                'Your Strategy Review was '.$status.'.',
                $form,
                'strategy_review_forms',
            );
        }

        CacheInvalidationService::onStratReviewChange();

        return back()->with('success', 'Strategy Review status updated.');
    }

    /** @return array<string, string> */
    private function validatedData(Request $request): array
    {
        $rules = [];
        foreach (self::FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:5000'];
        }
        Validator::make($request->all(), $rules)->validate();
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = $request->string($field)->toString();
        }

        return $data;
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
