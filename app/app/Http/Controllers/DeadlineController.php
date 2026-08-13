<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeadlineControl;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class DeadlineController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $deadlines = DeadlineControl::query()
            ->orderBy('role')
            ->get();

        return Inertia::render('Deadlines/Index', [
            'deadlines' => $deadlines,
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
            'end_time' => ['nullable', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $deadline = DeadlineControl::query()->findOrFail($role);

        $before = [
            'enabled' => $deadline->enabled,
            'end_time' => $deadline->end_time?->toDateTimeString(),
        ];

        $deadline->fill([
            'enabled' => $request->boolean('enabled'),
            'end_time' => $request->filled('end_time') ? $request->date('end_time') : null,
            'message' => $request->filled('message') ? $request->string('message')->toString() : null,
            'updated_by' => $this->userId($request),
        ]);
        $deadline->save();

        Cache::forget("pgs_deadline_{$role}");

        $this->audit->record(
            $this->userId($request),
            'deadline.updated',
            'deadline_control',
            $role,
            before: $before,
            after: [
                'enabled' => $deadline->enabled,
                'end_time' => $deadline->end_time?->toDateTimeString(),
            ],
            request: $request,
        );

        return back()->with('success', 'Deadline updated.');
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
