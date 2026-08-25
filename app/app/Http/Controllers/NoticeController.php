<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
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
        $paginated = CacheInvalidationService::remember('notice', 'index', function (): array {
            $notices = Notice::query()
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString();

            $notices->getCollection()->transform(fn (Notice $notice): array => $this->present($notice));

            return $notices->toArray();
        }, 60);

        return Inertia::render('Notices/Index', [
            'notices' => $paginated,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Explicit raster formats only: the generic `image` rule accepts
            // SVG, which must not be served inline.
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
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

        CacheInvalidationService::onNoticeChange();

        return back()->with('success', 'Notice published.');
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
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

        CacheInvalidationService::onNoticeChange();

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

        CacheInvalidationService::onNoticeChange();

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
        $dirty = false;

        foreach (['image', 'video'] as $kind) {
            if (! $request->hasFile($kind)) {
                continue;
            }

            $stored = $request->file($kind)?->store('notices', 'public');

            if (is_string($stored)) {
                $oldPath = $notice->{$kind};
                $notice->{$kind} = $stored;
                $dirty = true;

                if (is_string($oldPath)) {
                    $disk->delete($oldPath);
                }
            }
        }

        if ($dirty) {
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

        // Existing records may contain a relative upload path. Only legacy
        // public asset directories are searched — never the repo root,
        // config, or storage (which holds .env and backup archives).
        foreach ([base_path('../img'), base_path('../uploads')] as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) {
                continue;
            }

            $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
            $candidate = realpath($realRoot.DIRECTORY_SEPARATOR.$relative);
            $prefix = rtrim($realRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

            if ($candidate !== false && is_file($candidate) && str_starts_with($candidate, $prefix)) {
                return $candidate;
            }
        }

        return null;
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
