<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
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
        $albums = DB::table('gallery_albums as a')
            ->leftJoin('gallery_photos as p', 'p.album_id', '=', 'a.id')
            ->select('a.id', 'a.name', 'a.description', 'a.created_at', 'a.updated_at', DB::raw('COUNT(p.id) as photo_count'))
            ->groupBy('a.id', 'a.name', 'a.description', 'a.created_at', 'a.updated_at')
            ->orderByDesc('a.created_at')
            ->get();

        $photos = DB::table('gallery_photos')->orderByDesc('uploaded_at')->get();

        return Inertia::render('Gallery/Index', [
            'albums' => $albums,
            'photos' => $photos->groupBy('album_id'),
        ]);
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
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

        return back()->with('success', 'Album created.');
    }

    public function destroyAlbum(Request $request, int $album): RedirectResponse
    {
        $photos = DB::table('gallery_photos')->where('album_id', $album)->get();

        foreach ($photos as $photo) {
            if (($photo->filename ?? '') !== '') {
                Storage::disk('local')->delete($this->toStr($photo->filename));
            }
        }

        DB::table('gallery_photos')->where('album_id', $album)->delete();
        DB::table('gallery_albums')->where('id', $album)->delete();

        $this->audit->record(
            $this->userId($request),
            'gallery.album_deleted',
            'gallery_albums',
            (string) $album,
            request: $request,
        );

        return back()->with('success', 'Album deleted.');
    }

    public function storePhoto(Request $request, int $album): RedirectResponse
    {
        if (! DB::table('gallery_albums')->where('id', $album)->exists()) {
            abort(404);
        }

        Validator::make($request->all(), [
            'caption' => ['nullable', 'string', 'max:2000'],
            'photo' => ['required', 'file', 'image', 'max:10240'],
        ])->validate();

        $file = $request->file('photo');
        $stored = $file->store('uploads/gallery', 'local');

        if ($stored === false) {
            return back()->with('error', 'Could not store the photo.');
        }

        $id = DB::table('gallery_photos')->insertGetId([
            'album_id' => $album,
            'filename' => $stored,
            'caption' => $request->filled('caption') ? $request->string('caption')->toString() : null,
            'uploaded_at' => now(),
        ]);

        $this->audit->record(
            $this->userId($request),
            'gallery.photo_created',
            'gallery_photos',
            (string) $id,
            request: $request,
        );

        return back()->with('success', 'Photo uploaded.');
    }

    public function destroyPhoto(Request $request, int $photo): RedirectResponse
    {
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
}
