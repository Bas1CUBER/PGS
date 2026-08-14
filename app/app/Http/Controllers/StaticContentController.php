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
            'structuredContent' => $this->loadStructuredContent($page['content_type']),
            'canManage' => $request->user()?->isAdmin() ?? false,
        ]);
    }

    public function saveStructured(Request $request, string $slug): RedirectResponse
    {
        $page = ContentPageRegistry::find($slug);

        if ($page === null || $page['content_type'] === 'image') {
            abort(404);
        }

        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin(), 403);

        if ($page['content_type'] === 'charter') {
            Validator::make($request->all(), [
                'vision' => ['required', 'string', 'max:5000'],
                'mission' => ['required', 'string', 'max:5000'],
                'core_values' => ['required', 'string', 'max:2000'],
            ])->validate();

            $lines = preg_split('/\r\n|\r|\n/', $request->string('core_values')->toString());
            $lines = $lines === false ? [] : $lines;
            $values = array_values(array_filter(
                array_map(static fn (string $line): string => trim($line), $lines),
                static fn (string $line): bool => $line !== '',
            ));
            $this->writeStructured('charter', [
                'vision' => $request->string('vision')->toString(),
                'mission' => $request->string('mission')->toString(),
                'core_values' => $values,
            ]);
        } elseif ($page['content_type'] === 'pathway') {
            $rawPanels = $request->input('panels', []);
            if (is_string($rawPanels)) {
                $rawPanels = json_decode($rawPanels, true);
            }
            Validator::make(['panels' => $rawPanels], ['panels' => ['required', 'array', 'max:20']])->validate();
            $panels = array_map(static fn (mixed $panel): array => is_array($panel) ? [
                'type' => (string) ($panel['type'] ?? 'none'),
                'text' => (string) ($panel['text'] ?? ''),
                'image' => (string) ($panel['image'] ?? ''),
                'title' => (string) ($panel['title'] ?? ''),
                'status' => (string) ($panel['status'] ?? 'N/A'),
            ] : [], $rawPanels);
            $this->writeStructured('pathway', array_values($panels));
        } else {
            Validator::make($request->all(), ['matrix' => ['required', 'json', 'max:50000']])->validate();
            $matrix = json_decode($request->string('matrix')->toString(), true);
            abort_unless(is_array($matrix) && isset($matrix['columns'], $matrix['rows']), 422, 'The access matrix must include columns and rows.');
            $this->writeStructured('access', $matrix);
        }

        $this->audit->record($user->id, 'content.updated', 'content', $slug, request: $request);

        return back()->with('success', 'Content updated.');
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

    private function loadStructuredContent(string $type): mixed
    {
        if ($type === 'charter') {
            return $this->readStructured('charter', [
                'vision' => 'A prime TRC in Northern Luzon providing collaborative healthcare for substance dependents and the vulnerables by 2029.',
                'mission' => 'We Transform and Reach Communities towards a sustainable and inclusive treatment and rehabilitative care.',
                'core_values' => ['Compassion', 'Rectitude', 'Teamwork'],
            ]);
        }

        if ($type === 'pathway') {
            return $this->readStructured('pathway', []);
        }

        if ($type === 'access') {
            return $this->readStructured('access', [
                'columns' => ['Page', 'Employee', 'Focal', 'Admin'],
                'rows' => [
                    ['section' => 'Roadmaps'],
                    ['Page' => 'Roadmaps', 'Employee' => 'Table 1: Access, Table 2: Read Only', 'Focal' => 'Table 1: Access, Table 2: Read Only', 'Admin' => 'Table 1: ReadOnly, Table 2: Has Access'],
                    ['section' => 'Scorecard'],
                    ['Page' => 'Scorecard', 'Employee' => 'Read Only', 'Focal' => 'Read Only', 'Admin' => 'ADD, EDIT, DELETE sub items'],
                    ['Page' => 'Impact Indicator', 'Employee' => 'Read Only', 'Focal' => 'Read Only', 'Admin' => 'Whole access'],
                    ['section' => 'Performance Assessment'],
                    ['Page' => 'Operations Review', 'Employee' => 'Can download and upload document', 'Focal' => 'Can download and upload document', 'Admin' => 'Read only and can edit status'],
                    ['Page' => 'Strategy Review', 'Employee' => 'Read Only', 'Focal' => 'Read Only', 'Admin' => 'Upload a Document'],
                    ['Page' => 'Strategy Refresh', 'Employee' => 'Read Only', 'Focal' => 'Read Only', 'Admin' => 'Upload a Document'],
                    ['section' => 'Cascading'],
                    ['Page' => 'Communication Plan', 'Employee' => 'Read only, download and upload PDF', 'Focal' => 'Add, delete, edit status, DL and upload', 'Admin' => 'Add, delete, edit status'],
                    ['Page' => 'Cascading Activities', 'Employee' => 'Read only, download and upload PDF', 'Focal' => 'Read only, download and upload PDF', 'Admin' => 'Add, delete, edit status, DL and upload'],
                    ['Page' => 'Resources', 'Employee' => 'Read Only', 'Focal' => '', 'Admin' => 'Upload'],
                    ['section' => 'Governance'],
                    ['Page' => 'Governance Culture', 'Employee' => 'Upload file: img/pdf', 'Focal' => 'Upload file: img/pdf', 'Admin' => 'Edit, Save status'],
                    ['Page' => 'Governance Sharing', 'Employee' => 'Upload file: img/pdf', 'Focal' => 'Upload file: img/pdf', 'Admin' => 'Edit, Save status'],
                    ['section' => 'Organization'],
                    ['Page' => 'Office of Strategy Management', 'Employee' => 'View/Read Only', 'Focal' => 'View/Read Only', 'Admin' => 'Edit'],
                    ['Page' => 'PGS Core Team', 'Employee' => 'View/Read Only', 'Focal' => 'View/Read Only', 'Admin' => 'Edit'],
                    ['Page' => 'Multi-Sector Governance System', 'Employee' => 'View/Read Only', 'Focal' => 'View/Read Only', 'Admin' => 'Edit'],
                ],
            ]);
        }

        return null;
    }

    private function readStructured(string $type, mixed $fallback): mixed
    {
        $path = $this->structuredPath($type);

        if (! is_file($path)) {
            return $fallback;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return $data ?? $fallback;
    }

    private function writeStructured(string $type, mixed $data): void
    {
        $dir = base_path('../data');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->structuredPath($type), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function structuredPath(string $type): string
    {
        return base_path('../data/'.match ($type) {
            'charter' => 'charter_statements.json',
            'pathway' => 'pgs_pathway.json',
            'access' => 'user_access_matrix.json',
            default => throw new \InvalidArgumentException('Unknown content type.'),
        });
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
