<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class GalleryController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $data = CacheInvalidationService::remember('gallery', 'index', function (): array {
            $albums = DB::table('gallery_albums as a')
                ->leftJoin('gallery_photos as p', 'p.album_id', '=', 'a.id')
                ->select('a.id', 'a.name', 'a.description', 'a.created_at', 'a.updated_at', DB::raw('COUNT(p.id) as photo_count'))
                ->groupBy('a.id', 'a.name', 'a.description', 'a.created_at', 'a.updated_at')
                ->orderByDesc('a.created_at')
                ->get();

            $photos = DB::table('gallery_photos')->orderByDesc('uploaded_at')->get();

            return [
                'albums' => $albums,
                'photos' => $photos->groupBy('album_id'),
            ];
        }, 60);

        return Inertia::render('Gallery/Index', $data);
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        // Defense-in-depth alongside the route middleware.
        $this->assertCanManage($request);
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $id = DB::table('gallery_albums')->insertGetId([
            'name' => $request->string('name')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        $this->audit->record(
            $this->userId($request),
            'gallery.album_created',
            'gallery_albums',
            (string) $id,
            request: $request,
        );

        CacheInvalidationService::onGalleryChange();

        return back()->with('success', 'Album created.');
    }

    public function destroyAlbum(Request $request, int $album): RedirectResponse
    {
        // Defense-in-depth alongside the route middleware.
        $this->assertCanManage($request);
        abort_unless(DB::table('gallery_albums')->where('id', $album)->exists(), 404);
        $photos = DB::table('gallery_photos')->where('album_id', $album)->get();

        // Both table deletes are atomic; files are removed afterwards as
        // best-effort so a storage failure cannot orphan rows.
        DB::transaction(function () use ($album): void {
            DB::table('gallery_photos')->where('album_id', $album)->delete();
            DB::table('gallery_albums')->where('id', $album)->delete();
        });

        foreach ($photos as $photo) {
            if (($photo->filename ?? '') !== '') {
                Storage::disk('local')->delete($this->toStr($photo->filename));
            }
        }

        $this->audit->record(
            $this->userId($request),
            'gallery.album_deleted',
            'gallery_albums',
            (string) $album,
            request: $request,
        );

        CacheInvalidationService::onGalleryChange();

        return back()->with('success', 'Album deleted.');
    }

    public function updateAlbum(Request $request, int $album): RedirectResponse
    {
        // Defense-in-depth alongside the route middleware.
        $this->assertCanManage($request);
        abort_unless(DB::table('gallery_albums')->where('id', $album)->exists(), 404);
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        DB::table('gallery_albums')->where('id', $album)->update([
            'name' => $request->string('name')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
            'updated_at' => now(),
        ]);

        $this->audit->record($this->userId($request), 'gallery.album_updated', 'gallery_albums', (string) $album, request: $request);

        CacheInvalidationService::onGalleryChange();

        return back()->with('success', 'Album updated.');
    }

    public function storePhoto(Request $request, int $album): RedirectResponse
    {
        // Defense-in-depth alongside the route middleware.
        $this->assertCanManage($request);
        if (! DB::table('gallery_albums')->where('id', $album)->exists()) {
            abort(404);
        }

        // Merge both inputs BEFORE validating the count: appending the
        // single `photo` after validation would bypass the max by one.
        $photos = $request->file('photos');
        $files = is_array($photos) ? $photos : [];
        $single = $request->file('photo');
        if ($single !== null) {
            $files[] = $single;
        }
        if ($files === []) {
            return back()->withErrors(['photos' => 'Select at least one image.']);
        }

        Validator::make(
            ['photos' => $files],
            ['photos' => ['array', 'min:1', 'max:30']],
        )->validate();

        Validator::make($request->all(), [
            'caption' => ['nullable', 'string', 'max:2000'],
            // Explicit raster formats only: the generic `image` rule accepts
            // SVG, which must not be served inline.
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ])->validate();

        $caption = $request->filled('caption') ? $request->string('caption')->toString() : null;
        $created = 0;
        $failed = 0;
        foreach ($files as $file) {
            $stored = $file->store('uploads/gallery', 'local');
            if ($stored === false) {
                $failed++;

                continue;
            }

            DB::table('gallery_photos')->insert([
                'album_id' => $album,
                'filename' => $stored,
                'caption' => $caption,
                'uploaded_at' => now(),
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('error', 'Could not store the photo.');
        }

        $this->audit->record(
            $this->userId($request),
            'gallery.photo_created',
            'gallery_photos',
            null,
            after: ['created' => $created, 'failed' => $failed],
            request: $request,
        );

        CacheInvalidationService::onGalleryChange();

        $message = $created === 1 ? 'Photo uploaded.' : "{$created} photos uploaded.";

        return back()->with($failed > 0 ? 'error' : 'success', $failed > 0 ? "{$message} ({$failed} file(s) could not be stored.)" : $message);
    }

    public function updatePhoto(Request $request, int $photo): RedirectResponse
    {
        // Defense-in-depth alongside the route middleware.
        $this->assertCanManage($request);
        abort_unless(DB::table('gallery_photos')->where('id', $photo)->exists(), 404);
        Validator::make($request->all(), [
            'caption' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        DB::table('gallery_photos')->where('id', $photo)->update([
            'caption' => $request->filled('caption') ? $request->string('caption')->toString() : null,
        ]);
        $this->audit->record($this->userId($request), 'gallery.photo_updated', 'gallery_photos', (string) $photo, request: $request);

        return back()->with('success', 'Photo caption updated.');
    }

    public function destroyPhoto(Request $request, int $photo): RedirectResponse
    {
        // Defense-in-depth alongside the route middleware.
        $this->assertCanManage($request);
        $row = DB::table('gallery_photos')->where('id', $photo)->first();

        if ($row === null) {
            abort(404);
        }

        if (($row->filename ?? '') !== '') {
            Storage::disk('local')->delete($this->toStr($row->filename));
        }

        DB::table('gallery_photos')->where('id', $photo)->delete();

        $this->audit->record(
            $this->userId($request),
            'gallery.photo_deleted',
            'gallery_photos',
            (string) $photo,
            request: $request,
        );

        CacheInvalidationService::onGalleryChange();

        return back()->with('success', 'Photo deleted.');
    }

    public function photoFile(Request $request, int $photo): SymfonyResponse
    {
        $row = DB::table('gallery_photos')->where('id', $photo)->first();

        if ($row === null) {
            abort(404);
        }

        $path = $this->toStr($row->filename ?? '');
        $disk = Storage::disk('local');

        if ($path !== '' && $disk->exists($path)) {
            return $disk->response($path);
        }

        $legacy = base_path('../gallery_uploads/'.basename($path));
        if ($path !== '' && is_file($legacy)) {
            return response()->file($legacy);
        }

        abort(404);
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

    private function toStr(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    /**
     * Defense-in-depth re-check mirroring the route-level role:admin,focal
     * middleware, so these mutations stay protected regardless of where the
     * controller is mounted.
     */
    private function assertCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->isAdmin() || $user->isFocal()), 403);
    }
}
