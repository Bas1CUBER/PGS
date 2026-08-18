<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\UploadModuleRegistry;
use App\Services\AuditLogService;
use App\Services\UploadModuleService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class UploadModuleController extends Controller
{
    public function __construct(
        private readonly UploadModuleService $uploads,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);

        return Inertia::render('Uploads/Index', [
            'modules' => collect(UploadModuleRegistry::modules())
                ->filter(fn (array $module, string $slug): bool => $this->uploads->canAccessSlug($user, $slug))
                ->map(fn (array $module, string $slug): array => ['slug' => $slug] + $module)
                ->values()
                ->all(),
        ]);
    }

    public function legacyShow(Request $request, string $slug): Response
    {
        return $this->show($request, $slug);
    }

    public function show(Request $request, string $slug): Response
    {
        $module = $this->resolveModule($slug);
        $user = $this->userOrFail($request);
        $this->assertModuleAccess($user, $slug);

        $statusFilter = $request->string('status')->toString();
        $rows = $this->uploads->listRows($module, $slug, $statusFilter !== '' ? $statusFilter : null);
        $stats = $this->uploads->governanceStats($module, $slug);
        $templates = $this->uploads->templates($slug, $module);

        return Inertia::render('Uploads/Show', [
            'module' => $module + [
                'templates' => $templates,
                'upload_base_url' => $this->uploads->uploadRouteUrl($slug, 'index'),
                'template_upload_url' => $this->uploads->uploadRouteUrl($slug, 'templates.store'),
                'can_manage_templates' => $user->isAdmin(),
            ],
            'rows' => $rows,
            'filters' => ['status' => $request->string('status')->toString()],
            'stats' => $stats,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $module = $this->resolveModule($slug);
        $user = $this->userOrFail($request);
        $this->assertModuleAccess($user, $slug);

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

        $file = $request->file('file');

        if ($file->getMimeType() !== 'application/zip' && (int) $file->getSize() > 25600 * 1024) {
            return back()->withErrors(['file' => 'Non-ZIP uploads must be 25 MB or smaller.']);
        }

        $data = [];
        if ($module['has_title']) {
            $data['title'] = $request->string('title')->toString();
        }
        if ($module['has_description']) {
            $data['description'] = $request->filled('description') ? $request->string('description')->toString() : null;
        }

        try {
            $this->uploads->storeUpload($module, $user, $file, $data, $slug);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', ucfirst((string) $module['singular']).' uploaded.');
    }

    public function download(Request $request, string $slug, int $id): SymfonyResponse
    {
        $module = $this->resolveModule($slug);
        $this->assertModuleAccess($this->userOrFail($request), $slug);

        $row = DB::table($module['table'])->where('id', $id)->first();
        if ($row === null) {
            abort(404);
        }

        $rowArr = (array) $row;
        $name = (string) ($rowArr['original_name'] ?? 'file');
        $path = (string) ($rowArr['filename'] ?? '');
        $safe = $this->uploads->safeFilename($name);

        $disk = Storage::disk('local');
        if ($path !== '' && $disk->exists($path)) {
            $this->recordAudit($request, $slug, $id, 'downloaded', $module['table']);

            return $disk->download($path, $safe);
        }

        $legacy = $this->uploads->legacyPath($path);
        if ($legacy !== null) {
            $this->recordAudit($request, $slug, $id, 'downloaded', $module['table']);

            return response()->download($legacy, $safe);
        }

        abort(404);
    }

    public function destroy(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = $this->resolveModule($slug);
        $user = $this->userOrFail($request);
        $this->assertModuleAccess($user, $slug);

        $this->uploads->deleteUpload($module, $user, $id, $slug);

        return back()->with('success', ucfirst((string) $module['singular']).' deleted.');
    }

    public function updateStatus(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = $this->resolveModule($slug);

        if (! $module['has_status']) {
            abort(404);
        }

        $user = $this->userOrFail($request);
        $this->assertModuleAccess($user, $slug);

        if (! $user->isAdmin() && ! $user->isFocal()) {
            abort(403);
        }

        Validator::make($request->all(), [
            'status' => ['required', 'in:'.implode(',', (array) ($module['status_values'] ?? []))],
        ])->validate();

        $this->uploads->updateStatus($module, $user, $id, $request->string('status')->toString(), $slug);

        return back()->with('success', 'Status updated.');
    }

    public function templateStore(Request $request, string $slug): RedirectResponse
    {
        $module = $this->resolveModule($slug);
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin(), 403);

        Validator::make($request->all(), [
            'label' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:51200'],
        ])->validate();

        $this->uploads->storeTemplate($user, $slug, $request->string('label')->toString(), $request->file('file'));

        return back()->with('success', 'Template saved.');
    }

    public function templateDownload(Request $request, string $slug, int $template): SymfonyResponse
    {
        $module = $this->resolveModule($slug);
        $this->assertModuleAccess($this->userOrFail($request), $slug);

        $row = $this->uploads->findTemplate($slug, $template);
        if ($row === null) {
            abort(404);
        }
        $rowFilename = (string) $row->filename;
        if (! Storage::disk('local')->exists($rowFilename)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $rowFilename,
            $this->uploads->safeFilename((string) $row->original_name),
        );
    }

    public function templateDestroy(Request $request, string $slug, int $template): RedirectResponse
    {
        $module = $this->resolveModule($slug);
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin(), 403);

        $this->uploads->deleteTemplate($slug, $template);

        return back()->with('success', 'Template removed.');
    }

    private function resolveModule(string $slug): array
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        return $module;
    }

    private function assertModuleAccess(User $user, string $slug): void
    {
        abort_unless($this->uploads->canAccessSlug($user, $slug), 403);
    }

    private function recordAudit(Request $request, string $slug, int $id, string $action, string $table): void
    {
        $user = $this->userOrFail($request);
        app(AuditLogService::class)->record(
            $user->id,
            "upload.{$slug}.{$action}",
            $table,
            (string) $id,
            request: $request,
        );
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
}
