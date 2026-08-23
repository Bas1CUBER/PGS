<?php

declare(strict_types=1);

use App\Enums\RoadmapBlockType;
use App\Models\RoadmapBlock;
use App\Models\RoadmapItem;
use App\Models\RoadmapTitle;
use App\Models\User;

it('lists roadmaps with titles and items', function (): void {
    $user = User::factory()->employee()->create();
    $user->pageAccess()->create(['roadmaps' => true, 'scorecard' => false, 'performance_assessment' => false, 'cascading' => false, 'governance' => false]);
    $title = RoadmapTitle::query()->create(['title' => 'Governance', 'sort_order' => 1]);
    RoadmapItem::query()->create(['title_id' => $title->id, 'sub_letter' => 'A', 'sub_label' => 'Item A', 'page_slug' => 'item-a', 'sort_order' => 1]);

    $this->actingAs($user)
        ->get('/roadmaps')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Roadmaps/Index')
            ->has('titles', 1)
            ->where('titles.0.items.0.sub_label', 'Item A')
            ->has('titles.0.items.0.blocks'));
});

it('enforces the page access matrix on roadmaps', function (): void {
    $user = User::factory()->employee()->create();
    $user->pageAccess()->create([
        'roadmaps' => false,
        'scorecard' => true,
        'performance_assessment' => true,
        'cascading' => true,
        'governance' => true,
    ]);

    $this->actingAs($user)
        ->get('/roadmaps')
        ->assertForbidden();
});

it('creates a title and item', function (): void {
    $user = User::factory()->focal()->create();
    $user->pageAccess()->create(['roadmaps' => true, 'scorecard' => false, 'performance_assessment' => false, 'cascading' => false, 'governance' => false]);

    $this->actingAs($user)
        ->post('/roadmaps/titles', ['title' => 'New Section'])
        ->assertRedirect();

    $title = RoadmapTitle::query()->where('title', 'New Section')->first();
    expect($title)->not->toBeNull();

    $this->actingAs($user)
        ->post("/roadmaps/titles/{$title->id}/items", ['sub_label' => 'First item'])
        ->assertRedirect();

    expect(RoadmapItem::query()->where('title_id', $title->id)->count())->toBe(1);
});

it('creates and updates blocks with JSON content', function (): void {
    $user = User::factory()->focal()->create();
    $user->pageAccess()->create(['roadmaps' => true, 'scorecard' => false, 'performance_assessment' => false, 'cascading' => false, 'governance' => false]);
    $title = RoadmapTitle::query()->create(['title' => 'Section', 'sort_order' => 1]);
    $item = RoadmapItem::query()->create(['title_id' => $title->id, 'sub_letter' => 'A', 'sub_label' => 'Item', 'page_slug' => 'item', 'sort_order' => 1]);

    $this->actingAs($user)
        ->post("/roadmaps/items/{$item->id}/blocks", [
            'block_type' => RoadmapBlockType::DashboardStat->value,
            'content' => ['label' => 'Bed capacity', 'value' => '120'],
        ])
        ->assertRedirect();

    $block = RoadmapBlock::query()->where('item_id', $item->id)->first();
    expect($block)->not->toBeNull()
        ->and($block->block_type)->toBe(RoadmapBlockType::DashboardStat)
        ->and($block->content)->toBe(['label' => 'Bed capacity', 'value' => '120']);

    $this->actingAs($user)
        ->put("/roadmaps/blocks/{$block->id}", [
            'content' => ['label' => 'Bed capacity', 'value' => '150'],
        ])
        ->assertRedirect();

    expect($block->fresh()->content)->toBe(['label' => 'Bed capacity', 'value' => '150']);
});

it('reorders items within a title', function (): void {
    $user = User::factory()->focal()->create();
    $user->pageAccess()->create(['roadmaps' => true, 'scorecard' => false, 'performance_assessment' => false, 'cascading' => false, 'governance' => false]);
    $title = RoadmapTitle::query()->create(['title' => 'Section', 'sort_order' => 1]);
    $a = RoadmapItem::query()->create(['title_id' => $title->id, 'sub_letter' => 'A', 'sub_label' => 'A', 'page_slug' => 'a', 'sort_order' => 1]);
    $b = RoadmapItem::query()->create(['title_id' => $title->id, 'sub_letter' => 'B', 'sub_label' => 'B', 'page_slug' => 'b', 'sort_order' => 2]);

    $this->actingAs($user)
        ->post("/roadmaps/items/{$b->id}/reorder", ['direction' => 'up'])
        ->assertRedirect();

    expect($a->fresh()->sort_order)->toBe(2)
        ->and($b->fresh()->sort_order)->toBe(1);
});

it('deletes an item with its blocks', function (): void {
    $user = User::factory()->focal()->create();
    $user->pageAccess()->create(['roadmaps' => true, 'scorecard' => false, 'performance_assessment' => false, 'cascading' => false, 'governance' => false]);
    $title = RoadmapTitle::query()->create(['title' => 'Section', 'sort_order' => 1]);
    $item = RoadmapItem::query()->create(['title_id' => $title->id, 'sub_letter' => 'A', 'sub_label' => 'A', 'page_slug' => 'a', 'sort_order' => 1]);
    RoadmapBlock::query()->create(['item_id' => $item->id, 'block_type' => RoadmapBlockType::Paragraph->value, 'sort_order' => 1, 'content' => []]);

    $this->actingAs($user)
        ->delete("/roadmaps/items/{$item->id}")
        ->assertRedirect();

    expect(RoadmapItem::query()->find($item->id))->toBeNull()
        ->and(RoadmapBlock::query()->where('item_id', $item->id)->count())->toBe(0);
});
