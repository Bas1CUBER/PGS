<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class UploadModuleService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
        private readonly PageAccessService $access,
        private readonly DeadlineService $deadlines,
    ) {}

    public function canAccessSlug(User $user, string $slug): bool
    {
        $gate = match ($slug) {
            'resources', 'cascading-activities', 'communication-plan' => 'cascading',
            'governance-culture', 'governance-sharing' => 'governance',
            'operations-review', 'strategy-review', 'strategy-refresh' => 'performance_assessment',
            default => null,
        };

        return $gate === null || ! $this->access->hasMatrix($user) || $this->access->can($user, $gate);
    }

    /**
     * @param  array{table: string, has_status: bool, status_values: list<string>|null, uploader_fk: string, has_title: bool, has_description: bool, singular: string, label: string, slug?: string}  $module
     */
    public function initialStatus(array $module): string
    {
        if (($module['slug'] ?? '') === 'governance-culture' || ($module['slug'] ?? '') === 'governance-sharing') {
            return 'In Progress';
        }

        return in_array('Pending', $module['status_values'] ?? [], true) ? 'Pending' : (($module['status_values'] ?? [])[0] ?? 'Pending');
    }

    /**
     * @param  array{table: string, has_status: bool, status_values: list<string>|null, uploader_fk: string, has_title: bool, has_description: bool, singular: string, label: string}  $module
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, id: int}
     */
    public function storeUpload(array $module, User $user, UploadedFile $file, array $data, string $slug): array
    {
        $this->deadlines->enforce($user);

        $stored = $file->store('uploads/'.$slug, 'local');
        if ($stored === false) {
            throw new \RuntimeException('Could not store the file.');
        }

        $data['filename'] = $stored;
        $data['original_name'] = $file->getClientOriginalName();
        $data['file_size'] = $file->getSize();
        $data['mime_type'] = $file->getMimeType();
        $data['uploaded_at'] = now();
        $data[$module['uploader_fk']] = $user->id;

        // Double-click / retry guard: reject an identical submission (same
        // user, same file name + size) inside a short window instead of
        // silently creating a duplicate Pending row.
        $duplicate = DB::table($module['table'])
            ->where($module['uploader_fk'], $user->id)
            ->where('original_name', $data['original_name'])
            ->where('file_size', $data['file_size'])
            ->where('uploaded_at', '>=', now()->subSeconds(60))
            ->exists();

        if ($duplicate) {
            Storage::disk('local')->delete($stored);

            throw new \RuntimeException('This file was just submitted. Check the list below before uploading again.');
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

        return ['data' => $data, 'id' => $id];
    }

    /**
     * @param  array{table: string, uploader_fk: string, singular: string, label: string}  $module
     */
    public function deleteUpload(array $module, User $user, int $id, string $slug): void
    {
        $row = DB::table($module['table'])->where('id', $id)->first();
        if ($row === null) {
            abort(404);
        }

        /** @var array<string, mixed> $rowArr */
        $rowArr = (array) $row;
        $ownerId = is_numeric($rowArr[$module['uploader_fk']] ?? null) ? (int) $rowArr[$module['uploader_fk']] : 0;

        if (! $user->isAdmin() && ! $user->isFocal() && $ownerId !== $user->id) {
            abort(403);
        }

        $filename = (string) ($rowArr['filename'] ?? '');
        if ($filename !== '') {
            Storage::disk('local')->delete($filename);
        }

        DB::table($module['table'])->where('id', $id)->delete();

        $this->audit->record(
            $user->id,
            "upload.{$slug}.deleted",
            $module['table'],
            (string) $id,
        );
    }

    /**
     * @param  array{table: string, has_status: bool, status_values: list<string>|null, uploader_fk: string, singular: string, label: string}  $module
     */
    public function updateStatus(array $module, User $user, int $id, string $status, string $slug): void
    {
        $row = DB::table($module['table'])->where('id', $id)->first();
        if ($row === null) {
            abort(404);
        }

        $previousStatus = $row->status ?? null;
        $allowed = $module['status_values'] ?? [];
        if ($previousStatus !== null && ! in_array($previousStatus, $allowed, true)) {
            abort(422, 'Current status is not a valid transition source.');
        }

        DB::table($module['table'])->where('id', $id)->update([
            'status' => $status,
            'status_updated_at' => now(),
        ]);

        $this->audit->record(
            $user->id,
            "upload.{$slug}.status",
            $module['table'],
            (string) $id,
            before: ['status' => $previousStatus],
            after: ['status' => $status],
        );

        /** @var array<string, mixed> $rowArr */
        $rowArr = (array) $row;
        $ownerId = is_numeric($rowArr[$module['uploader_fk']] ?? null) ? (int) $rowArr[$module['uploader_fk']] : 0;
        $type = $status === 'Approved' ? NotificationType::Approved : ($status === 'Returned' ? NotificationType::Returned : NotificationType::Edit);
        if ($ownerId > 0 && $ownerId !== $user->id) {
            $this->notifications->create($ownerId, $type, 'Upload status updated', "Your {$module['singular']} in {$module['label']} is now {$status}.", $id, $module['table']);
        }
    }

    /**
     * @param  array{table: string, has_status: bool, status_values: list<string>|null, uploader_fk: string, has_title: bool, has_description: bool}  $module
     * @return array<int, array{id: int, title: string|null, description: string|null, filename: string, original_name: string, file_size: int, status: string|null, uploaded_at: string, uploader: string|null, uploader_id: int}>
     */
    public function listRows(array $module, string $slug, ?string $statusFilter = null): array
    {
        $table = $module['table'];

        $query = DB::table($table)
            ->when($module['has_status'] && $statusFilter !== null && in_array($statusFilter, $module['status_values'] ?? [], true), fn ($q) => $q->where('status', $statusFilter))
            ->orderByDesc('uploaded_at')
            ->paginate(20)
            ->withQueryString();

        /** @var array<int, array<string, mixed>> $rowsRaw */
        $rowsRaw = collect($query->items())->map(static fn (object $r): array => (array) $r)->values()->all();
        $uploaderIds = collect($rowsRaw)->pluck($module['uploader_fk'])->unique()->all();
        $uploaders = $uploaderIds !== []
            ? DB::table('users')->whereIn('id', $uploaderIds)->pluck('email', 'id')->all()
            : [];

        return collect($rowsRaw)->map(function (array $row) use ($module, $uploaders): array {
            /** @var array<string, mixed> $row */
            $uploaderId = (int) ($row[$module['uploader_fk']] ?? 0);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $module['has_title'] ? ($row['title'] ?? null) === null ? null : (string) $row['title'] : null,
                'description' => $module['has_description'] ? ($row['description'] ?? null) === null ? null : (string) $row['description'] : null,
                'filename' => (string) ($row['filename'] ?? ''),
                'original_name' => (string) ($row['original_name'] ?? ''),
                'file_size' => (int) ($row['file_size'] ?? 0),
                'status' => $module['has_status'] ? ($row['status'] ?? null) === null ? null : (string) $row['status'] : null,
                'uploaded_at' => (string) ($row['uploaded_at'] ?? ''),
                'uploader' => $uploaders[$uploaderId] ?? null,
                'uploader_id' => $uploaderId,
            ];
        })->values()->all();
    }

    /**
     * @param  array{table: string}  $module
     * @return array{total: int, pdf: int, image: int, approved: int, in_progress: int, returned: int}|null
     */
    public function governanceStats(array $module, string $slug): ?array
    {
        if (! in_array($slug, ['governance-culture', 'governance-sharing'], true)) {
            return null;
        }

        $table = $module['table'];

        return [
            'total' => DB::table($table)->count(),
            'pdf' => DB::table($table)->where('doc_type', 'PDF')->count(),
            'image' => DB::table($table)->where('doc_type', 'Image')->count(),
            'approved' => DB::table($table)->where('status', 'Approved')->count(),
            'in_progress' => DB::table($table)->where('status', 'In Progress')->count(),
            'returned' => DB::table($table)->where('status', 'Returned')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<int, array{label: string, file: string, preview: bool, source: string, id?: int, url: string}>
     */
    public function templates(string $slug, array $module): array
    {
        /** @var list<array{label: string, file: string, preview: bool}> $templates */
        $templates = $module['templates'] ?? [];

        $staticTemplates = collect($templates)
            ->filter(fn (array $template): bool => is_file(base_path('../img/'.$template['file'])))
            ->map(fn (array $template): array => [
                'label' => $template['label'],
                'file' => $template['file'],
                'preview' => $template['preview'],
                'source' => 'static',
                'url' => route('legacy-img', ['name' => $template['file']], absolute: false),
            ]);

        $managedTemplates = DB::table('upload_module_templates')
            ->where('slug', $slug)
            ->orderBy('label')
            ->get()
            ->map(function (object $template) use ($slug): array {
                /** @var array<string, mixed> $template */
                $template = (array) $template;

                return [
                    'label' => (string) ($template['label'] ?? ''),
                    'file' => (string) ($template['original_name'] ?? ''),
                    'preview' => false,
                    'source' => 'managed',
                    'id' => (int) ($template['id'] ?? 0),
                    'url' => $this->templateRouteUrl($slug, (int) ($template['id'] ?? 0)),
                ];
            });

        return $staticTemplates->concat($managedTemplates)->values()->all();
    }

    public function storeTemplate(User $user, string $slug, string $label, UploadedFile $file): void
    {
        $existing = DB::table('upload_module_templates')->where('slug', $slug)->where('label', $label)->first();
        if ($existing !== null) {
            /** @var array<string, mixed> $existingArr */
            $existingArr = (array) $existing;
            Storage::disk('local')->delete((string) ($existingArr['filename'] ?? ''));
        }

        $path = $file->store('templates/'.$slug, 'local');
        if ($path === false) {
            abort(500, 'Could not store the template.');
        }

        DB::table('upload_module_templates')->updateOrInsert(
            ['slug' => $slug, 'label' => $label],
            [
                'filename' => $path,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $user->id,
                'updated_at' => now(),
                'created_at' => $existing !== null && isset($existing->created_at) ? $existing->created_at : now(),
            ],
        );
    }

    public function deleteTemplate(string $slug, int $templateId): void
    {
        $row = DB::table('upload_module_templates')->where('id', $templateId)->where('slug', $slug)->first();
        if ($row === null) {
            abort(404);
        }

        /** @var array<string, mixed> $rowArr */
        $rowArr = (array) $row;
        Storage::disk('local')->delete((string) ($rowArr['filename'] ?? ''));
        DB::table('upload_module_templates')->where('id', $templateId)->delete();
    }

    public function findTemplate(string $slug, int $templateId): ?object
    {
        return DB::table('upload_module_templates')->where('id', $templateId)->where('slug', $slug)->first();
    }

    private function templateRouteUrl(string $slug, int $templateId): string
    {
        $routeName = $slug.'.upload.templates.download';

        if (Route::has($routeName)) {
            return route($routeName, ['template' => $templateId], absolute: false);
        }

        return route('uploads.templates.download', ['slug' => $slug, 'template' => $templateId], absolute: false);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function uploadRouteUrl(string $slug, string $action, array $parameters = []): string
    {
        $routeName = $slug.'.upload.'.$action;

        if (Route::has($routeName)) {
            return route($routeName, $parameters, absolute: false);
        }

        return route('uploads.'.$action, ['slug' => $slug] + $parameters, absolute: false);
    }

    public function safeFilename(string $name): string
    {
        $name = basename(str_replace(["\r", "\n"], '', trim($name)));

        return $name !== '' && $name !== '.' && $name !== '..' ? $name : 'file';
    }

    public function legacyPath(string $path): ?string
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
