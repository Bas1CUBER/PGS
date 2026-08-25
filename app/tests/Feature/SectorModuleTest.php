<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the sector modules', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($user)
        ->get('/sectors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sectors/Index')
            ->has('modules', 7));
});

it('shows a sector module with its data', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
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
    $user = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($user)
        ->get('/sectors/not-a-pillar')
        ->assertNotFound();
});

it('updates a sector indicator row', function (): void {
    $user = User::factory()->focal()->withPageAccess()->create();
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
    $user = User::factory()->focal()->withPageAccess()->create();
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

it('denies employees direct progress updates', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
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
        ])
        ->assertForbidden();

    expect(DB::table('resilience_progress')->where('id', $id)->value('status'))->toBe('Ongoing');
});

it('does not leak pending changes through the shared page cache', function (): void {
    $focal = User::factory()->focal()->withPageAccess()->create();
    $employee = User::factory()->employee()->withPageAccess()->create();
    DB::table('culture')->insert([
        'category' => 'Patient Safety Culture',
        'year' => 2025,
        'description' => 'Baseline.',
    ]);
    DB::table('progress_pending_changes')->insert([
        'module' => 'culture',
        'change_type' => 'add_row',
        'category' => 'Secret submission',
        'year' => 2031,
        'description' => 'pending row',
        'submitted_by' => $employee->id,
        'submitted_at' => now(),
        'decision' => 'Pending',
    ]);

    // Admin/focal warms the cache first.
    $this->actingAs($focal)
        ->get('/sectors/culture')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pendingChanges', 1));

    // The employee must not receive the review queue in their props.
    $this->actingAs($employee)
        ->get('/sectors/culture')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pendingChanges', 0));
});

it('rejects a second decision on an already-reviewed change', function (): void {
    $focal = User::factory()->focal()->withPageAccess()->create();
    $changeId = DB::table('progress_pending_changes')->insertGetId([
        'module' => 'culture',
        'change_type' => 'add_row',
        'category' => 'Dup check',
        'year' => 2030,
        'description' => 'row',
        'submitted_by' => $focal->id,
        'submitted_at' => now(),
        'decision' => 'Pending',
    ]);

    $payload = ['decision' => 'Approved'];

    $this->actingAs($focal)->post("/sectors/culture/pending/{$changeId}/decision", $payload)->assertRedirect();
    $this->actingAs($focal)->post("/sectors/culture/pending/{$changeId}/decision", $payload)->assertStatus(409);

    expect((int) DB::table('culture')->where('category', 'Dup check')->count())->toBe(1)
        ->and(DB::table('progress_pending_changes')->where('id', $changeId)->value('decision'))->toBe('Approved')
        ->and(AuditLog::query()->where('action', 'sector.culture.pending_Approved')->exists())->toBeTrue();
});
