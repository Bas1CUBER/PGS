<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class NoticeController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $notices = Notice::query()
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Notices/Index', [
            'notices' => $notices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $notice = Notice::query()->create([
            'title' => $request->string('title')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        $this->audit->record(
            $this->userId($request),
            'notice.created',
            'notices',
            (string) $notice->notice_id,
            after: ['title' => $notice->title],
            request: $request,
        );

        return back()->with('success', 'Notice published.');
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $notice->update([
            'title' => $request->string('title')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        $this->audit->record(
            $this->userId($request),
            'notice.updated',
            'notices',
            (string) $notice->notice_id,
            request: $request,
        );

        return back()->with('success', 'Notice updated.');
    }

    public function destroy(Request $request, Notice $notice): RedirectResponse
    {
        $notice->delete();

        $this->audit->record(
            $this->userId($request),
            'notice.deleted',
            'notices',
            null,
            before: ['title' => $notice->title],
            request: $request,
        );

        return back()->with('success', 'Notice deleted.');
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
