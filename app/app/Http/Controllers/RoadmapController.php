<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoadmapBlockType;
use App\Models\RoadmapBlock;
use App\Models\RoadmapItem;
use App\Models\RoadmapTitle;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use App\Services\DeadlineService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class RoadmapController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $titles = CacheInvalidationService::remember('roadmap', 'tree', function (): Collection {
            return RoadmapTitle::query()
                ->with('items.blocks')
                ->orderBy('sort_order')
                ->get();
        }, 60);

        return Inertia::render('Roadmaps/Index', [
            'titles' => $titles,
        ]);
    }

    public function storeTitle(Request $request): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
        ])->validate();

        $title = DB::transaction(function () use ($request): RoadmapTitle {
            $max = (int) RoadmapTitle::query()->max('sort_order');

            return RoadmapTitle::query()->create([
                'title' => $request->string('title')->toString(),
                'sort_order' => $max + 1,
            ]);
        });

        $this->audit->record(
            $this->userId($request),
            'roadmap.title_created',
            'roadmap_titles',
            (string) $title->id,
            after: ['title' => $title->title],
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Roadmap section added.');
    }

    public function updateTitle(Request $request, RoadmapTitle $title): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
        ])->validate();

        $title->update(['title' => $request->string('title')->toString()]);

        $this->audit->record(
            $this->userId($request),
            'roadmap.title_updated',
            'roadmap_titles',
            (string) $title->id,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Roadmap section updated.');
    }

    public function destroyTitle(Request $request, RoadmapTitle $title): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        $title->delete();

        $this->audit->record(
            $this->userId($request),
            'roadmap.title_deleted',
            'roadmap_titles',
            null,
            before: ['title' => $title->title],
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Roadmap section deleted.');
    }

    public function storeItem(Request $request, RoadmapTitle $title): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        app(DeadlineService::class)->enforce($user);

        Validator::make($request->all(), [
            'sub_label' => ['required', 'string', 'max:500'],
        ])->validate();

        $item = DB::transaction(function () use ($title, $request): RoadmapItem {
            RoadmapTitle::query()->whereKey($title->id)->lockForUpdate()->first();

            $max = (int) RoadmapItem::query()->where('title_id', $title->id)->max('sort_order');
            $letter = $this->nextLetter($max);

            return RoadmapItem::query()->create([
                'title_id' => $title->id,
                'sub_letter' => $letter,
                'sub_label' => $request->string('sub_label')->toString(),
                'page_slug' => Str::slug($request->string('sub_label')->toString()),
                'has_builder_page' => false,
                'sort_order' => $max + 1,
            ]);
        });

        $this->audit->record(
            $this->userId($request),
            'roadmap.item_created',
            'roadmap_items',
            (string) $item->id,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Roadmap item added.');
    }

    public function updateItem(Request $request, RoadmapItem $item): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        Validator::make($request->all(), [
            'sub_label' => ['required', 'string', 'max:500'],
        ])->validate();

        $item->update([
            'sub_label' => $request->string('sub_label')->toString(),
            'page_slug' => Str::slug($request->string('sub_label')->toString()),
        ]);

        $this->audit->record(
            $this->userId($request),
            'roadmap.item_updated',
            'roadmap_items',
            (string) $item->id,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Roadmap item updated.');
    }

    public function destroyItem(Request $request, RoadmapItem $item): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        $item->delete();

        $this->audit->record(
            $this->userId($request),
            'roadmap.item_deleted',
            'roadmap_items',
            null,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Roadmap item deleted.');
    }

    public function storeBlock(Request $request, RoadmapItem $item): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        Validator::make($request->all(), [
            'block_type' => ['required', 'in:'.implode(',', RoadmapBlockType::values())],
            'content' => ['nullable', 'array'],
        ])->validate();

        $blockType = $request->string('block_type')->toString();

        if ($blockType === RoadmapBlockType::Heading->value || $blockType === RoadmapBlockType::Paragraph->value) {
            Validator::make($request->all(), [
                'content.text' => ['required', 'string', 'max:5000'],
            ])->validate();
        } elseif ($blockType === RoadmapBlockType::Table->value) {
            Validator::make($request->all(), [
                'content.columns' => ['required', 'array', 'max:20'],
                'content.columns.*' => ['required', 'string', 'max:255'],
                'content.rows' => ['required', 'array', 'max:50'],
                'content.rows.*' => ['array', 'max:20'],
            ])->validate();
        } elseif ($blockType === RoadmapBlockType::DashboardStat->value) {
            Validator::make($request->all(), [
                'content.label' => ['required', 'string', 'max:255'],
                'content.value' => ['required', 'string', 'max:255'],
            ])->validate();
        }

        $max = (int) RoadmapBlock::query()->where('item_id', $item->id)->max('sort_order');

        RoadmapBlock::query()->create([
            'item_id' => $item->id,
            'block_type' => $request->string('block_type')->toString(),
            'sort_order' => $max + 1,
            'content' => $request->input('content') ?? [],
        ]);

        $this->audit->record(
            $this->userId($request),
            'roadmap.block_created',
            'roadmap_page_blocks',
            null,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Block added.');
    }

    public function updateBlock(Request $request, RoadmapBlock $block): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        Validator::make($request->all(), [
            'content' => ['nullable', 'array'],
        ])->validate();

        $type = $block->block_type->value;

        if ($type === RoadmapBlockType::Heading->value || $type === RoadmapBlockType::Paragraph->value) {
            Validator::make($request->all(), [
                'content.text' => ['required', 'string', 'max:5000'],
            ])->validate();
        } elseif ($type === RoadmapBlockType::Table->value) {
            Validator::make($request->all(), [
                'content.columns' => ['required', 'array', 'max:20'],
                'content.columns.*' => ['required', 'string', 'max:255'],
                'content.rows' => ['required', 'array', 'max:50'],
                'content.rows.*' => ['array', 'max:20'],
            ])->validate();
        } elseif ($type === RoadmapBlockType::DashboardStat->value) {
            Validator::make($request->all(), [
                'content.label' => ['required', 'string', 'max:255'],
                'content.value' => ['required', 'string', 'max:255'],
            ])->validate();
        }

        $block->update(['content' => $request->input('content') ?? []]);

        $this->audit->record(
            $this->userId($request),
            'roadmap.block_updated',
            'roadmap_page_blocks',
            (string) $block->id,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Block updated.');
    }

    public function destroyBlock(Request $request, RoadmapBlock $block): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        $block->delete();

        $this->audit->record(
            $this->userId($request),
            'roadmap.block_deleted',
            'roadmap_page_blocks',
            null,
            request: $request,
        );

        CacheInvalidationService::onRoadmapChange();

        return back()->with('success', 'Block deleted.');
    }

    public function reorderItem(Request $request, RoadmapItem $item): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($user->isAdmin() || $user->isFocal(), 403);

        Validator::make($request->all(), [
            'direction' => ['required', 'in:up,down'],
        ])->validate();

        $direction = $request->string('direction')->toString();

        DB::transaction(function () use ($item, $direction): void {
            $items = RoadmapItem::query()->where('title_id', $item->title_id)->orderBy('sort_order')->lockForUpdate()->get();
            $keys = $items->map(fn (RoadmapItem $i): int => (int) $i->getKey())->all();
            $index = array_search((int) $item->getKey(), $keys, true);

            if ($index === false) {
                abort(404);
            }

            $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($swapIndex < 0 || $swapIndex >= count($keys)) {
                return;
            }

            $a = $items->get($index);
            $b = $items->get($swapIndex);

            if ($a === null || $b === null) {
                return;
            }

            $orderA = $a->sort_order;
            $a->update(['sort_order' => $b->sort_order]);
            $b->update(['sort_order' => $orderA]);
        });

        CacheInvalidationService::onRoadmapChange();

        return back();
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

    /**
     * @throws AuthenticationException
     */
    private function userId(Request $request): int
    {
        return $this->userOrFail($request)->id;
    }

    private function nextLetter(int $max): string
    {
        $n = $max;
        $letter = '';

        do {
            $remainder = $n % 26;
            $letter = chr(65 + $remainder).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }
}
