<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\UploadModuleRegistry;
use App\Services\AuditLogService;
use App\Services\DeadlineService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    ) {}

    public function index(): Response
    {
        return Inertia::render('Uploads/Index', [
            'modules' => UploadModuleRegistry::modules(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

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
            ];
        })->values()->all();

        return Inertia::render('Uploads/Show', [
            'module' => $module,
            'rows' => $rows,
            'filters' => ['status' => $request->string('status')->toString()],
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        Validator::make($request->all(), [
            'title' => $module['has_title'] ? ['required', 'string', 'max:255'] : ['nullable'],
            'description' => $module['has_description'] ? ['nullable', 'string', 'max:5000'] : ['nullable'],
            'file' => ['required', 'file', 'max:25600'],
        ])->validate();

        $user = $this->userOrFail($request);

        app(DeadlineService::class)->enforce($user);

        $file = $request->file('file');
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
            $data['status'] = 'Pending';
        }

        $id = DB::table($module['table'])->insertGetId($data);

        $this->audit->record(
            $user->id,
            "upload.{$slug}.created",
            $module['table'],
            (string) $id,
            request: $request,
        );

        return back()->with('success', ucfirst($module['singular']).' uploaded.');
    }

    public function download(Request $request, string $slug, int $id): SymfonyResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $rowArr = (array) $row;
        $name = $this->toStr($rowArr['original_name'] ?? 'file');
        $path = $this->toStr($rowArr['filename'] ?? '');

        if ($path !== '' && $disk->exists($path)) {
            return $disk->download($path, $name);
        }

        // Legacy files live outside the app storage (repo ../uploads).
        $legacy = base_path('../uploads/'.ltrim($path, '/'));
        if ($path !== '' && is_file($legacy)) {
            return response()->download($legacy, $name);
        }

        abort(404);
    }

    public function destroy(Request $request, string $slug, int $id): RedirectResponse
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        $rowArr = (array) $row;

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

        Validator::make($request->all(), [
            'status' => ['required', 'in:'.implode(',', $module['status_values'] ?? [])],
        ])->validate();

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        DB::table($module['table'])->where('id', $id)->update([
            'status' => $request->string('status')->toString(),
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

        return back()->with('success', 'Status updated.');
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
}
