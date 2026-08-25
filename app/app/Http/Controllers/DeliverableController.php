<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliverableStatus;
use App\Enums\NotificationType;
use App\Models\Deliverable;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use App\Services\DeadlineService;
use App\Services\NotificationService;
use App\Services\TransitionsWorkflowService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DeliverableController extends Controller
{
    /**
     * @param  TransitionsWorkflowService<Deliverable>  $workflow
     */
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly TransitionsWorkflowService $workflow,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Deliverable::class);

        $user = $request->user();
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $isEmployee = $user?->isEmployee() === true;
        $page = (int) $request->query('page', '1');
        $cacheKey = ($isEmployee ? "emp:{$user->id}:" : '').$search.':'.$status.':p'.$page;

        $query = CacheInvalidationService::remember('deliverable', "index:{$cacheKey}", function () use ($search, $status, $isEmployee, $user): array {
            return Deliverable::query()
                ->with('uploader:id,name,email')
                ->when($search !== '', fn ($q) => $q->where(
                    fn ($q) => $q
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('division', 'like', '%'.$search.'%'),
                ))
                ->when(in_array($status, DeliverableStatus::values(), true), fn ($q) => $q->where('status', $status))
                ->when($isEmployee, fn ($q) => $q->where('uploaded_by', $user?->id))
                ->orderByDesc('target_date')
                ->paginate(20)
                ->withQueryString()
                ->toArray();
        }, 60);

        return Inertia::render('Deliverables/Index', [
            'deliverables' => $query,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => DeliverableStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Deliverable::class);

        return Inertia::render('Deliverables/Create', [
            'statuses' => DeliverableStatus::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Deliverable::class);

        app(DeadlineService::class)->enforce($this->userOrFail($request));

        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'form_type' => ['nullable', 'string', 'max:100'],
            'focal_person' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
            'status' => ['required', 'in:'.implode(',', DeliverableStatus::values())],
            'actual_date' => ['nullable', 'date'],
            'mov_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:25600'],
        ])->validate();

        $deliverable = new Deliverable;
        $this->fillFromRequest($deliverable, $request, includeStatus: true);

        if ($request->hasFile('mov_file')) {
            // A failed disk write must not silently produce a deliverable
            // without its MOV.
            $path = $request->file('mov_file')->store('deliverables', 'local');
            if ($path === false) {
                throw new \RuntimeException('Could not store the MOV file.');
            }
            $deliverable->mov_file = $path;
        }

        $deliverable->uploaded_by = $this->userId($request);
        $deliverable->save();

        $this->audit->record(
            $this->userId($request),
            'deliverable.created',
            'p_deliverables',
            (string) $deliverable->id,
            after: ['title' => $deliverable->title, 'status' => $deliverable->status?->value],
            request: $request,
        );

        $this->notifications->createForRolesExcept(
            ['admin', 'focal'],
            $deliverable->uploaded_by,
            NotificationType::Edit,
            'New deliverable created',
            ($this->userOrFail($request)->email).' created a new deliverable for tracking.',
            $deliverable->id,
            'p_deliverables',
        );

        CacheInvalidationService::onDeliverableChange();

        return redirect()->route('deliverables.index')->with('success', 'Deliverable created.');
    }

    public function edit(Request $request, Deliverable $deliverable): Response
    {
        $this->authorize('update', $deliverable);

        return Inertia::render('Deliverables/Edit', [
            'deliverable' => $deliverable,
            'statuses' => DeliverableStatus::values(),
        ]);
    }

    public function update(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $this->authorize('update', $deliverable);

        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'form_type' => ['nullable', 'string', 'max:100'],
            'focal_person' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
            // Status changes on update flow exclusively through the
            // transition endpoint; accepting-but-ignoring it here would be a
            // misleading contract.
            'status' => ['sometimes', 'required', 'in:'.implode(',', DeliverableStatus::values())],
            'actual_date' => ['nullable', 'date'],
            'mov_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:25600'],
        ])->validate();

        $before = ['title' => $deliverable->title, 'status' => $deliverable->status?->value];
        $previousMov = $deliverable->mov_file;
        $this->fillFromRequest($deliverable, $request);

        if ($request->hasFile('mov_file')) {
            $path = $request->file('mov_file')->store('deliverables', 'local');
            if ($path === false) {
                throw new \RuntimeException('Could not store the MOV file.');
            }
            $deliverable->mov_file = $path;
        }

        $deliverable->save();

        // The replaced file is removed only after the row points at the new
        // one, so a failure cannot leave a dangling path.
        if ($previousMov !== null && $previousMov !== $deliverable->mov_file) {
            Storage::disk('local')->delete($previousMov);
        }

        $this->audit->record(
            $this->userId($request),
            'deliverable.updated',
            'p_deliverables',
            (string) $deliverable->id,
            before: $before,
            after: ['title' => $deliverable->title, 'status' => $deliverable->status?->value],
            request: $request,
        );

        CacheInvalidationService::onDeliverableChange();

        return redirect()->route('deliverables.index')->with('success', 'Deliverable updated.');
    }

    public function destroy(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $this->authorize('delete', $deliverable);

        $filePath = $deliverable->mov_file;

        $deliverable->delete();

        // File removal happens after the DB row is committed: a storage
        // failure must not roll back an already-deleted record (and the
        // reverse would orphan the file).
        if ($filePath !== null) {
            Storage::disk('local')->delete($filePath);
        }

        $this->audit->record(
            $this->userId($request),
            'deliverable.deleted',
            'p_deliverables',
            null,
            before: ['title' => $deliverable->title],
            request: $request,
        );

        CacheInvalidationService::onDeliverableChange();

        return redirect()->route('deliverables.index')->with('success', 'Deliverable deleted.');
    }

    public function download(Request $request, Deliverable $deliverable): StreamedResponse
    {
        $this->authorize('view', $deliverable);

        if ($deliverable->mov_file === null || ! Storage::disk('local')->exists($deliverable->mov_file)) {
            abort(404);
        }

        $this->audit->record(
            $this->userId($request),
            'deliverable.downloaded',
            'p_deliverables',
            (string) $deliverable->id,
            request: $request,
        );

        $filename = Str::slug($deliverable->title ?? 'deliverable');
        $extension = pathinfo($deliverable->mov_file, PATHINFO_EXTENSION) !== '' ? pathinfo($deliverable->mov_file, PATHINFO_EXTENSION) : 'pdf';

        return Storage::disk('local')->download(
            $deliverable->mov_file,
            $filename !== '' ? $filename.'.'.$extension : 'deliverable.'.$extension,
        );
    }

    public function transition(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $this->authorize('update', $deliverable);

        Validator::make($request->all(), [
            'to' => ['required', 'in:'.implode(',', DeliverableStatus::values())],
        ])->validate();

        $this->workflow->transition(
            $deliverable,
            $request->string('to')->toString(),
            $this->userOrFail($request),
        );

        $ownerId = (int) $deliverable->uploaded_by;
        if ($ownerId > 0 && $ownerId !== $this->userId($request)) {
            $this->notifications->create(
                $ownerId,
                NotificationType::Edit,
                'Deliverable status updated',
                'Your deliverable is now '.$request->string('to')->toString().'.',
                $deliverable->id,
                'p_deliverables',
            );
        }

        CacheInvalidationService::onDeliverableChange();

        return back()->with('success', 'Deliverable status updated.');
    }

    private function fillFromRequest(Deliverable $deliverable, Request $request, bool $includeStatus = false): void
    {
        $deliverable->fill([
            'form_type' => $request->filled('form_type') ? $request->string('form_type')->toString() : null,
            'title' => $request->string('title')->toString(),
            'focal_person' => $request->filled('focal_person') ? $request->string('focal_person')->toString() : null,
            'division' => $request->filled('division') ? $request->string('division')->toString() : null,
            'target_date' => $request->filled('target_date') ? $request->date('target_date') : null,
            'actual_date' => $request->filled('actual_date') ? $request->date('actual_date') : null,
        ]);

        if ($includeStatus) {
            $deliverable->status = DeliverableStatus::from($request->string('status')->toString());
        }
    }

    /**
     * @throws AuthenticationException
     */
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
        return $this->userOrFail($request)->id;
    }
}
