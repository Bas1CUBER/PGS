<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $notices->getCollection()->transform(fn (Notice $notice): array => $this->present($notice));

        return Inertia::render('Notices/Index', [
            'notices' => $notices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'image', 'max:20480'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],
        ])->validate();

        $notice = Notice::query()->create([
            'title' => $request->string('title')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        $this->storeMedia($request, $notice);

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
            'image' => ['nullable', 'file', 'image', 'max:20480'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],
        ])->validate();

        $notice->update([
            'title' => $request->string('title')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        $this->storeMedia($request, $notice);

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
        $this->deleteMedia($notice);
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

    public function media(Notice $notice, string $kind): BinaryFileResponse
    {
        abort_unless(in_array($kind, ['image', 'video'], true), 404);

        $path = $notice->{$kind};
        abort_unless(is_string($path) && $path !== '', 404);

        $resolved = $this->resolveMediaPath($path);
        abort_if($resolved === null, 404);

        return response()->file($resolved);
    }

    /** @return array<string, mixed> */
    private function present(Notice $notice): array
    {
        return [
            'notice_id' => $notice->notice_id,
            'title' => $notice->title,
            'description' => $notice->description,
            'created_at' => $notice->created_at,
            'image_url' => $notice->image !== null ? route('notices.media', [$notice, 'image'], absolute: false) : null,
            'video_url' => $notice->video !== null ? route('notices.media', [$notice, 'video'], absolute: false) : null,
        ];
    }

    private function storeMedia(Request $request, Notice $notice): void
    {
        $disk = Storage::disk('public');

        foreach (['image', 'video'] as $kind) {
            if (! $request->hasFile($kind)) {
                continue;
            }

            $oldPath = $notice->{$kind};
            if (is_string($oldPath)) {
                $disk->delete($oldPath);
            }

            $stored = $request->file($kind)?->store('notices', 'public');

            if (is_string($stored)) {
                $notice->{$kind} = $stored;
            }
        }

        if ($notice->isDirty(['image', 'video'])) {
            $notice->save();
        }
    }

    private function deleteMedia(Notice $notice): void
    {
        $disk = Storage::disk('public');

        foreach ([$notice->image, $notice->video] as $path) {
            if (is_string($path)) {
                $disk->delete($path);
            }
        }
    }

    private function resolveMediaPath(string $path): ?string
    {
        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $disk->path($path);
        }

        // Existing records may contain a relative upload path. Resolve it
        // inside the application parent directory and reject traversal.
        $root = realpath(base_path('../'));
        if ($root === false) {
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
