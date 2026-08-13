<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\ContentPageRegistry;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class StaticContentController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $page = ContentPageRegistry::find($slug);

        if ($page === null) {
            abort(404);
        }

        return Inertia::render('Content/Show', [
            'page' => $page,
            'imageUrl' => $this->findImage($page['img_base']),
            'canManage' => $request->user()?->isAdmin() ?? false,
        ]);
    }

    public function replaceImage(Request $request, string $slug): RedirectResponse
    {
        $page = ContentPageRegistry::find($slug);

        if ($page === null) {
            abort(404);
        }

        $user = $this->userOrFail($request);

        if (! $user->isAdmin()) {
            abort(403);
        }

        Validator::make($request->all(), [
            'image' => ['required', 'file', 'image', 'max:20480'],
        ])->validate();

        $dir = base_path('../img');
        $base = $page['img_base'];

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $existing = glob($dir.'/'.$base.'.*');
        foreach ($existing === false ? [] : $existing as $old) {
            @unlink($old);
        }

        $ext = match ($request->file('image')->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => abort(422, 'Only JPG, PNG, or WEBP images are allowed.'),
        };

        $request->file('image')->move($dir, $base.'.'.$ext);

        $this->audit->record(
            $user->id,
            'content.image_replaced',
            'content',
            $slug,
            request: $request,
        );

        return back()->with('success', 'Image updated.');
    }

    private function findImage(string $base): ?string
    {
        $matches = glob(base_path('../img/'.$base.'.*'));
        $matches = $matches === false ? [] : $matches;

        if ($matches === []) {
            return null;
        }

        return '/legacy-img/'.rawurlencode(basename($matches[0]));
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
