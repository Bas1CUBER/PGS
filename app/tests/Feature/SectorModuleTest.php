<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the sector modules', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/sectors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sectors/Index')
            ->has('modules', 7));
});

it('shows a sector module with its data', function (): void {
    $user = User::factory()->employee()->create();
    DB::table('culture')->insert([
        'category' => 'Patient Safety Culture',
        'year' => 2025,
        'description' => 'Baseline assessment conducted.',
    ]);
    DB::table('culture_progress')->insert([
        'category' => 'Patient Safety Culture',
        'year' => 2025,
        'month' => 1,
        'status' => 'Accomplished',
        'remarks' => 'Done',
        'updated_by' => $user->id,
        'description' => 'Q1 milestone',
    ]);

    $this->actingAs($user)
        ->get('/sectors/culture')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sectors/Show')
            ->has('rows.data', 1)
            ->has('progress', 1)
            ->where('progress.0.status', 'Accomplished'));
});

it('rejects unknown sector slugs', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/sectors/not-a-pillar')
        ->assertNotFound();
});

it('updates a sector indicator row', function (): void {
    $user = User::factory()->employee()->create();
    $id = DB::table('collab')->insertGetId([
        'category' => 'Partnerships',
        'year' => 2025,
        'description' => 'Old description',
    ]);

    $this->actingAs($user)
        ->put("/sectors/collab/rows/{$id}", [
            'category' => 'Partnerships',
            'year' => '2026',
            'description' => 'New description',
        ])
        ->assertRedirect();

    expect(DB::table('collab')->where('id', $id)->value('description'))->toBe('New description')
        ->and(DB::table('collab')->where('id', $id)->value('year'))->toBe(2026);
});

it('updates sector progress with audit', function (): void {
    $user = User::factory()->employee()->create();
    $id = DB::table('resilience_progress')->insertGetId([
        'category' => 'Disaster Preparedness',
        'year' => 2025,
        'month' => 3,
        'status' => 'Ongoing',
        'updated_by' => $user->id,
        'description' => 'x',
    ]);

    $this->actingAs($user)
        ->put("/sectors/resilience/progress/{$id}", [
            'status' => 'Accomplished',
            'remarks' => 'All done',
        ])
        ->assertRedirect();

    expect(DB::table('resilience_progress')->where('id', $id)->value('status'))->toBe('Accomplished')
        ->and(DB::table('resilience_progress')->where('id', $id)->value('updated_by'))->toBe($user->id);

    expect(AuditLog::query()->where('action', 'sector.resilience.progress_updated')->exists())->toBeTrue();
});
