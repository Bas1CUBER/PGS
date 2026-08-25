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

    public function index(Request $request): Response
    {
        $page = (int) $request->query('page', '1');

        // paginate() reads ?page= from the current request, so the page must
        // be part of the cache key or one page's result is served to all.
        $paginated = CacheInvalidationService::remember('notice', "index:p{$page}", function (): array {
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
        $this->assertCanManage($request);
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
        $this->assertCanManage($request);
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
        $this->assertCanManage($request);
        $mediaPaths = array_filter(
            [$notice->image, $notice->video],
            static fn ($path): bool => is_string($path) && $path !== '',
        );

        // Row first, files after (mirrors DeliverableController): a failed
        // DB delete must never leave a row pointing at removed media.
        $notice->delete();

        Storage::disk('public')->delete($mediaPaths);

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
        $stalePaths = [];

        foreach (['image', 'video'] as $kind) {
            if (! $request->hasFile($kind)) {
                continue;
            }

            $stored = $request->file($kind)?->store('notices', 'public');

            if (is_string($stored)) {
                if (is_string($notice->{$kind})) {
                    $stalePaths[] = $notice->{$kind};
                }
                $notice->{$kind} = $stored;
                $dirty = true;
            }
        }

        // The new paths are persisted BEFORE the replaced files are removed,
        // so a failed save can never leave media-less rows.
        if ($dirty) {
            $notice->save();
        }

        foreach ($stalePaths as $path) {
            $disk->delete($path);
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

    /**
     * Defense-in-depth re-check mirroring the route-level role:admin,focal
     * middleware.
     */
    private function assertCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->isAdmin() || $user->isFocal()), 403);
    }
}
