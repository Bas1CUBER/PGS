<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\User;
use App\Modules\UploadModuleRegistry;
use App\Services\AuditLogService;
use App\Services\DeadlineService;
use App\Services\NotificationService;
use App\Services\PageAccessService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * One controller serves every upload module from UploadModuleRegistry:
 * list (with status filter), upload, download, delete, and focal
 * approve/return transitions.
 */
final class UploadModuleController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
        private readonly PageAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);

        return Inertia::render('Uploads/Index', [
            'modules' => collect(UploadModuleRegistry::modules())
                ->filter(fn (array $module, string $slug): bool => $this->canAccessSlug($user, $slug))
                ->map(fn (array $module, string $slug): array => ['slug' => $slug] + $module)
                ->values()
                ->all(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $user = $this->userOrFail($request);
        $this->authorizeModule($user, $slug);

        $table = $module['table'];

        $query = DB::table($table)
            ->when($module['has_status'], fn ($q) => $q
                ->when($module['status_values'] !== null && in_array($request->string('status')->toString(), $module['status_values'], true), fn ($q) => $q->where('status', $request->string('status')->toString())))
            ->orderByDesc('uploaded_at')
            ->paginate(20)
            ->withQueryString();

        $rowsRaw = collect($query->items())->map(static fn (object $r): array => (array) $r)->values()->all();
        $uploaderIds = collect($rowsRaw)->pluck($module['uploader_fk'])->unique()->all();
        $uploaders = $uploaderIds !== []
            ? DB::table('users')->whereIn('id', $uploaderIds)->pluck('email', 'id')->all()
            : [];

        $rows = collect($rowsRaw)->map(function (array $row) use ($module, $uploaders): array {
            $uploaderId = $this->toInt($row[$module['uploader_fk']] ?? 0);

            return [
                'id' => $this->toInt($row['id'] ?? 0),
                'title' => $module['has_title'] ? $this->toNullableStr($row['title'] ?? null) : null,
                'description' => $module['has_description'] ? $this->toNullableStr($row['description'] ?? null) : null,
                'filename' => $this->toStr($row['filename'] ?? null),
                'original_name' => $this->toStr($row['original_name'] ?? null),
                'file_size' => $this->toInt($row['file_size'] ?? 0),
                'status' => $module['has_status'] ? $this->toNullableStr($row['status'] ?? null) : null,
                'uploaded_at' => $this->toStr($row['uploaded_at'] ?? null),
                'uploader' => $uploaders[$uploaderId] ?? null,
                'uploader_id' => $uploaderId,
            ];
        })->values()->all();

        $stats = null;
        if (in_array($slug, ['governance-culture', 'governance-sharing'], true)) {
            $stats = [
                'total' => DB::table($table)->count(),
                'pdf' => DB::table($table)->where('doc_type', 'PDF')->count(),
                'image' => DB::table($table)->where('doc_type', 'Image')->count(),
                'approved' => DB::table($table)->where('status', 'Approved')->count(),
                'in_progress' => DB::table($table)->where('status', 'In Progress')->count(),
                'returned' => DB::table($table)->where('status', 'Returned')->count(),
            ];
        }

        $staticTemplates = collect($module['templates'] ?? [])
            ->filter(fn (array $template): bool => is_file(base_path('../img/'.$template['file'])))
            ->map(fn (array $template): array => $template + ['source' => 'static', 'url' => route('legacy-img', ['name' => $template['file']], absolute: false)]);
        $managedTemplates = DB::table('upload_module_templates')
            ->where('slug', $slug)
            ->orderBy('label')
            ->get()
            ->map(fn (object $template): array => [
                'label' => $this->toStr($template->label),
                'file' => $this->toStr($template->original_name),
                'preview' => false,
                'source' => 'managed',
                'id' => $this->toInt($template->id),
                'url' => route('uploads.templates.download', ['slug' => $slug, 'template' => $template->id], absolute: false),
            ]);

        return Inertia::render('Uploads/Show', [
            'module' => $module + [
                'templates' => $staticTemplates->concat($managedTemplates)->values()->all(),
                'template_upload_url' => route('uploads.templates.store', ['slug' => $slug], absolute: false),
                'can_manage_templates' => $user->isAdmin(),
            ],
            'rows' => $rows,
            'filters' => ['status' => $request->string('status')->toString()],
            'stats' => $stats,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $user = $this->userOrFail($request);
        $this->authorizeModule($user, $slug);

        Validator::make($request->all(), [
            'title' => $module['has_title'] ? ['required', 'string', 'max:255'] : ['nullable'],
            'description' => $module['has_description'] ? ['nullable', 'string', 'max:5000'] : ['nullable'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,zip',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/jpeg,image/png,image/webp,application/zip',
                'max:51200',
            ],
        ])->validate();

        app(DeadlineService::class)->enforce($user);

        $file = $request->file('file');

        if ($file->getMimeType() !== 'application/zip' && (int) $file->getSize() > 25600 * 1024) {
            return back()->withErrors(['file' => 'Non-ZIP uploads must be 25 MB or smaller.']);
        }

        $stored = $file->store('uploads/'.$slug, 'local');

        if ($stored === false) {
            return back()->with('error', 'Could not store the file.');
        }

        $data = [
            'filename' => $stored,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_at' => now(),
            $module['uploader_fk'] => $user->id,
        ];

        if ($module['has_title']) {
            $data['title'] = $request->string('title')->toString();
        }

        if ($module['has_description']) {
            $data['description'] = $request->filled('description') ? $request->string('description')->toString() : null;
        }

        if ($module['has_status']) {
            $data['status'] = $this->initialStatus($module);
        }

        if (Schema::hasColumn($module['table'], 'doc_type')) {
            $data['doc_type'] = str_starts_with((string) $file->getMimeType(), 'image/') ? 'Image' : 'PDF';
        }

        $id = DB::table($module['table'])->insertGetId($data);

        $this->audit->record(
            $user->id,
            "upload.{$slug}.created",
            $module['table'],
            (string) $id,
            request: $request,
        );

        $this->notifications->createForRolesExcept(
            ['admin', 'focal'],
            $user->id,
            NotificationType::Upload,
            'New upload submitted',
            $user->email.' submitted a new '.$module['singular'].' in '.$module['label'].'.',
            $id,
            $module['table'],
        );

        return back()->with('success', ucfirst($module['singular']).' uploaded.');
    }

    public function download(Request $request, string $slug, int $id): SymfonyResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $this->authorizeModule($this->userOrFail($request), $slug);

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $rowArr = (array) $row;
        $name = $this->toStr($rowArr['original_name'] ?? 'file');
        $path = $this->toStr($rowArr['filename'] ?? '');

        if ($path !== '' && $disk->exists($path)) {
            $this->audit->record(
                $this->userOrFail($request)->id,
                "upload.{$slug}.downloaded",
                $module['table'],
                (string) $id,
                request: $request,
            );

            return $disk->download($path, $this->safeFilename($name));
        }

        // Legacy files live outside the app storage (repo ../uploads).
        $legacy = $this->legacyPath($path);
        if ($legacy !== null) {
            $this->audit->record(
                $this->userOrFail($request)->id,
                "upload.{$slug}.downloaded",
                $module['table'],
                (string) $id,
                request: $request,
            );

            return response()->download($legacy, $this->safeFilename($name));
        }

        abort(404);
    }

    public function destroy(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $this->authorizeModule($this->userOrFail($request), $slug);

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        $rowArr = (array) $row;
        $user = $this->userOrFail($request);
        $ownerId = $this->toInt($rowArr[$module['uploader_fk']] ?? 0);

        if (! $user->isAdmin() && ! $user->isFocal() && $ownerId !== $user->id) {
            abort(403);
        }

        if ($this->toStr($rowArr['filename'] ?? null) !== '') {
            Storage::disk('local')->delete($this->toStr($rowArr['filename'] ?? null));
        }

        DB::table($module['table'])->where('id', $id)->delete();

        $this->audit->record(
            $this->userOrFail($request)->id,
            "upload.{$slug}.deleted",
            $module['table'],
            (string) $id,
            request: $request,
        );

        return back()->with('success', ucfirst($module['singular']).' deleted.');
    }

    public function updateStatus(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null || ! $module['has_status']) {
            abort(404);
        }

        $user = $this->userOrFail($request);
        $this->authorizeModule($user, $slug);

        if (! $user->isAdmin() && ! $user->isFocal()) {
            abort(403);
        }

        Validator::make($request->all(), [
            'status' => ['required', 'in:'.implode(',', $module['status_values'] ?? [])],
        ])->validate();

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        $status = $request->string('status')->toString();
        DB::table($module['table'])->where('id', $id)->update([
            'status' => $status,
            'status_updated_at' => now(),
        ]);

        $this->audit->record(
            $this->userOrFail($request)->id,
            "upload.{$slug}.status",
            $module['table'],
            (string) $id,
            before: ['status' => $row->status ?? null],
            after: ['status' => $request->string('status')->toString()],
            request: $request,
        );

        $ownerId = $this->toInt(((array) $row)[$module['uploader_fk']] ?? 0);
        $type = $status === 'Approved' ? NotificationType::Approved : ($status === 'Returned' ? NotificationType::Returned : NotificationType::Edit);
        if ($ownerId > 0 && $ownerId !== $user->id) {
            $this->notifications->create($ownerId, $type, 'Upload status updated', "Your {$module['singular']} in {$module['label']} is now {$status}.", $id, $module['table']);
        }

        return back()->with('success', 'Status updated.');
    }

    public function templateStore(Request $request, string $slug): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);
        $user = $this->userOrFail($request);
        abort_if($module === null, 404);
        abort_unless($user->isAdmin(), 403);

        Validator::make($request->all(), [
            'label' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:51200'],
        ])->validate();

        $existing = DB::table('upload_module_templates')->where('slug', $slug)->where('label', $request->string('label')->toString())->first();
        if ($existing !== null) {
            Storage::disk('local')->delete($this->toStr($existing->filename));
        }

        $path = $request->file('file')->store('templates/'.$slug, 'local');
        abort_if($path === false, 500, 'Could not store the template.');

        DB::table('upload_module_templates')->updateOrInsert(
            ['slug' => $slug, 'label' => $request->string('label')->toString()],
            [
                'filename' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'uploaded_by' => $user->id,
                'updated_at' => now(),
                'created_at' => $existing !== null && isset($existing->created_at) ? $existing->created_at : now(),
            ],
        );

        return back()->with('success', 'Template saved.');
    }

    public function templateDownload(Request $request, string $slug, int $template): SymfonyResponse
    {
        $module = UploadModuleRegistry::find($slug);
        $user = $this->userOrFail($request);
        abort_if($module === null, 404);
        $this->authorizeModule($user, $slug);
        $row = DB::table('upload_module_templates')->where('id', $template)->where('slug', $slug)->first();
        abort_if($row === null || ! Storage::disk('local')->exists($this->toStr($row->filename)), 404);

        return Storage::disk('local')->download($this->toStr($row->filename), $this->safeFilename($this->toStr($row->original_name)));
    }

    public function templateDestroy(Request $request, string $slug, int $template): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);
        $user = $this->userOrFail($request);
        abort_if($module === null, 404);
        abort_unless($user->isAdmin(), 403);
        $row = DB::table('upload_module_templates')->where('id', $template)->where('slug', $slug)->first();
        abort_if($row === null, 404);
        Storage::disk('local')->delete($this->toStr($row->filename));
        DB::table('upload_module_templates')->where('id', $template)->delete();

        return back()->with('success', 'Template removed.');
    }

    private function authorizeModule(User $user, string $slug): void
    {
        abort_unless($this->canAccessSlug($user, $slug), 403);
    }

    private function canAccessSlug(User $user, string $slug): bool
    {
        $gate = match ($slug) {
            'resources', 'cascading-activities', 'communication-plan' => 'cascading',
            'governance-culture', 'governance-sharing' => 'governance',
            'operations-review', 'strategy-review', 'strategy-refresh' => 'performance_assessment',
            default => null,
        };

        return $gate === null || ! $this->access->hasMatrix($user) || $this->access->can($user, $gate);
    }

    /** @param array{status_values: list<string>|null, slug?: string} $module */
    private function initialStatus(array $module): string
    {
        if (($module['slug'] ?? '') === 'governance-culture' || ($module['slug'] ?? '') === 'governance-sharing') {
            return 'In Progress';
        }

        return in_array('Pending', $module['status_values'] ?? [], true) ? 'Pending' : (($module['status_values'] ?? [])[0] ?? 'Pending');
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

    private function toStr(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    private function toNullableStr(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function toInt(mixed $value): int
    {
        return (int) $value;
    }

    private function safeFilename(string $name): string
    {
        $name = basename(str_replace(["\r", "\n"], '', trim($name)));

        return $name !== '' && $name !== '.' && $name !== '..' ? $name : 'file';
    }

    private function legacyPath(string $path): ?string
    {
        $root = realpath(base_path('../uploads'));

        if ($root === false || $path === '') {
            return null;
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $candidate = realpath($root.DIRECTORY_SEPARATOR.$relative);
        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($candidate === false || ! is_file($candidate) || ! str_starts_with($candidate, $prefix)) {
            return null;
        }

        return $candidate;
    }
}
