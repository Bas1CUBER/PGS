<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

function pendingChange(string $module, array $overrides = []): int
{
    return DB::table('progress_pending_changes')->insertGetId(array_merge([
        'module' => $module,
        'change_type' => 'add_row',
        'category' => 'New initiative',
        'year' => 2026,
        'description' => 'Proposed indicator',
        'submitted_by' => 1,
        'submitted_at' => now(),
        'decision' => 'Pending',
    ], $overrides));
}

it('lets admins add a sector row directly', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/sectors/culture/rows', [
            'category' => 'Direct addition',
            'year' => '2026',
            'description' => 'Added by admin',
        ])
        ->assertRedirect();

    expect(DB::table('culture')->where('category', 'Direct addition')->exists())->toBeTrue()
        ->and(DB::table('progress_pending_changes')->where('category', 'Direct addition')->exists())->toBeFalse();
});

it('routes employee sector row additions through pending approval', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/sectors/culture/rows', [
            'category' => 'Employee proposal',
            'year' => '2026',
            'description' => 'Proposed by employee',
        ])
        ->assertRedirect();

    expect(DB::table('culture')->where('category', 'Employee proposal')->exists())->toBeFalse();

    $pending = DB::table('progress_pending_changes')->where('category', 'Employee proposal')->first();
    expect($pending)->not->toBeNull()
        ->and($pending->change_type)->toBe('add_row')
        ->and($pending->submitted_by)->toBe($employee->id)
        ->and($pending->decision)->toBe('Pending');
});

it('validates sector row additions', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/sectors/culture/rows', ['category' => 'Missing fields'])
        ->assertSessionHasErrors(['year', 'description']);
});

it('denies employees deleting sector rows', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = DB::table('culture')->insertGetId([
        'category' => 'Protected row',
        'year' => 2025,
        'description' => 'x',
    ]);

    $this->actingAs($employee)
        ->delete("/sectors/culture/rows/{$id}")
        ->assertForbidden();

    expect(DB::table('culture')->where('id', $id)->exists())->toBeTrue();
});

it('lets admins delete sector rows', function (): void {
    $admin = User::factory()->admin()->create();
    $id = DB::table('culture')->insertGetId([
        'category' => 'Disposable row',
        'year' => 2025,
        'description' => 'x',
    ]);

    $this->actingAs($admin)
        ->delete("/sectors/culture/rows/{$id}")
        ->assertRedirect();

    expect(DB::table('culture')->where('id', $id)->exists())->toBeFalse();
});

it('denies employees deciding pending changes', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = pendingChange('culture');

    $this->actingAs($employee)
        ->post("/sectors/culture/pending/{$id}/decision", ['decision' => 'Approved'])
        ->assertForbidden();

    expect(DB::table('progress_pending_changes')->where('id', $id)->value('decision'))->toBe('Pending');
});

it('approves a pending add_row into the sector table', function (): void {
    $admin = User::factory()->admin()->create();
    $id = pendingChange('culture', ['category' => 'Approved proposal', 'description' => 'Will land']);

    $this->actingAs($admin)
        ->post("/sectors/culture/pending/{$id}/decision", ['decision' => 'Approved'])
        ->assertRedirect();

    expect(DB::table('progress_pending_changes')->where('id', $id)->value('decision'))->toBe('Approved')
        ->and(DB::table('culture')->where('category', 'Approved proposal')->exists())->toBeTrue();
});

it('applies an approved save_progress change to the progress table', function (): void {
    $admin = User::factory()->admin()->create();
    $id = pendingChange('culture', [
        'change_type' => 'save_progress',
        'category' => 'Ongoing initiative',
        'month' => 3,
        'status' => 'Ongoing',
        'remarks' => 'On track',
    ]);

    $this->actingAs($admin)
        ->post("/sectors/culture/pending/{$id}/decision", ['decision' => 'Approved'])
        ->assertRedirect();

    $progress = DB::table('culture_progress')->where('category', 'Ongoing initiative')->first();
    expect($progress)->not->toBeNull()
        ->and($progress->status)->toBe('Ongoing')
        ->and($progress->updated_by)->toBe($admin->id);
});

it('rejects a pending change without touching the sector tables', function (): void {
    $admin = User::factory()->admin()->create();
    $id = pendingChange('culture', ['category' => 'Rejected proposal']);

    $this->actingAs($admin)
        ->post("/sectors/culture/pending/{$id}/decision", ['decision' => 'Rejected'])
        ->assertRedirect();

    expect(DB::table('progress_pending_changes')->where('id', $id)->value('decision'))->toBe('Rejected')
        ->and(DB::table('culture')->where('category', 'Rejected proposal')->exists())->toBeFalse();
});

it('404s deciding an already-decided change', function (): void {
    $admin = User::factory()->admin()->create();
    $id = pendingChange('culture', ['decision' => 'Approved']);

    $this->actingAs($admin)
        ->post("/sectors/culture/pending/{$id}/decision", ['decision' => 'Rejected'])
        ->assertNotFound();
});

it('denies employees locking sector detail rows', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = DB::table('client_satisfaction_values')->insertGetId([
        'table_key' => 'k',
        'division_key' => 'd',
        'year' => 2025,
        'annual' => '90',
        'created_by' => $employee->id,
    ]);

    $this->actingAs($employee)
        ->post("/sectors/culture/client-satisfaction/{$id}/lock")
        ->assertForbidden();
});

it('lets admins and focals toggle sector detail row locks', function (): void {
    $id = DB::table('client_satisfaction_values')->insertGetId([
        'table_key' => 'k',
        'division_key' => 'd',
        'year' => 2025,
        'annual' => '90',
        'created_by' => User::factory()->employee()->withPageAccess()->create()->id,
    ]);

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->post("/sectors/culture/client-satisfaction/{$id}/lock")
        ->assertRedirect();

    expect(DB::table('client_satisfaction_values')->where('id', $id)->value('locked'))->toBe(1);

    $this->actingAs(User::factory()->admin()->create())
        ->post("/sectors/culture/client-satisfaction/{$id}/lock")
        ->assertRedirect();

    expect(DB::table('client_satisfaction_values')->where('id', $id)->value('locked'))->toBe(0);
});

it('refuses to lock rows in tables without a lock column', function (): void {
    $admin = User::factory()->admin()->create();
    $id = DB::table('training_pct_personnel')->insertGetId([
        'section' => 'ICU',
        'personnel' => '5',
        'is_head' => 0,
    ]);

    $this->actingAs($admin)
        ->post("/sectors/training/percentage-trained/{$id}/lock")
        ->assertStatus(422);
});

it('lets admins add and delete sector detail rows', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/sectors/resilience/gvr', [
            'indicator' => 'Green coverage',
            'share' => '40',
            'y2024' => '35',
        ])
        ->assertRedirect();

    $row = DB::table('resilience_gvr')->where('indicator', 'Green coverage')->first();
    expect($row)->not->toBeNull();

    $this->actingAs($admin)
        ->delete("/sectors/resilience/gvr/{$row->id}")
        ->assertRedirect();

    expect(DB::table('resilience_gvr')->where('id', $row->id)->exists())->toBeFalse();
});

it('denies employees deleting sector detail rows', function (): void {
    $id = DB::table('resilience_gvr')->insertGetId([
        'indicator' => 'Protected detail',
        'share' => '40',
    ]);

    $this->actingAs(User::factory()->employee()->withPageAccess()->create())
        ->delete("/sectors/resilience/gvr/{$id}")
        ->assertForbidden();
});

it('exports a sector detail table as CSV', function (): void {
    DB::table('resilience_gvr')->insertGetId([
        'indicator' => 'Green coverage',
        'share' => '40',
    ]);

    $this->actingAs(User::factory()->employee()->withPageAccess()->create())
        ->get('/sectors/resilience/gvr/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
