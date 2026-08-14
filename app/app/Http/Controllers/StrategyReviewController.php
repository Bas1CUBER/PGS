<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
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

    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);
        $forms = DB::table('strategy_review_forms as f')
            ->join('users as u', 'u.id', '=', 'f.employee_id')
            ->when($user->isEmployee(), fn ($query) => $query->where('f.employee_id', $user->id))
            ->orderByDesc('f.updated_at')
            ->get(['f.id', 'f.employee_id', 'f.form_data', 'f.pdf_filename', 'f.status', 'f.created_at', 'f.updated_at', 'u.email as employee_email'])
            ->map(fn (\stdClass $form): array => [
                'id' => $this->idValue($form->id),
                'employee_id' => $this->idValue($form->employee_id),
                'employee_email' => is_scalar($form->employee_email) ? (string) $form->employee_email : '',
                'data' => $this->decodeFormData($form->form_data),
                'pdf_filename' => $form->pdf_filename,
                'status' => $form->status,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at,
            ])->values()->all();

        return Inertia::render('StrategyReview/Index', [
            'forms' => $forms,
            'canReview' => $user->isAdmin() || $user->isFocal(),
            'userId' => $user->id,
            'canEditAny' => $user->isAdmin(),
            'fields' => self::FIELDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $data = $this->validatedData($request);
        $status = $request->string('status')->toString() === 'Submitted' ? 'Submitted' : 'Draft';

        $id = DB::table('strategy_review_forms')->insertGetId([
            'employee_id' => $user->id,
            'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        return back()->with('success', $status === 'Submitted' ? 'Strategy Review submitted.' : 'Strategy Review draft saved.');
    }

    public function update(Request $request, int $form): RedirectResponse
    {
        $user = $this->userOrFail($request);
        $existing = DB::table('strategy_review_forms')->where('id', $form)->first();
        abort_unless($existing !== null && ($user->isAdmin() || $this->idValue($existing->employee_id) === $user->id), 403);

        $data = $this->validatedData($request);
        $status = $request->string('status')->toString() === 'Submitted' ? 'Submitted' : 'Draft';
        DB::table('strategy_review_forms')->where('id', $form)->update([
            'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'updated_at' => now(),
        ]);

        return back()->with('success', $status === 'Submitted' ? 'Strategy Review submitted.' : 'Strategy Review draft saved.');
    }

    public function review(Request $request, int $form): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);
        Validator::make($request->all(), ['status' => ['required', 'in:Approved,Returned']])->validate();
        $existing = DB::table('strategy_review_forms')->where('id', $form)->first();
        abort_if($existing === null, 404);
        abort_unless($this->idValue($existing->employee_id) !== $user->id, 403, 'A reviewer cannot approve their own form.');
        $status = $request->string('status')->toString();
        DB::table('strategy_review_forms')->where('id', $form)->update(['status' => $status, 'updated_at' => now()]);

        $ownerId = $this->idValue($existing->employee_id);
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
