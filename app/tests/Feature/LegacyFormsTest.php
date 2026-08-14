<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

function opcrRow(array $overrides = []): int
{
    return DB::table('performance_targets')->insertGetId(array_merge([
        'success_indicator' => 'Reduce relapse rate by 5%',
        'division_accountable' => 'Clinical Services',
        'annual_target' => '5%',
        'quarter1_target' => '1%',
    ], $overrides));
}

it('renders every registered annex workspace', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();

    foreach (['annex-b', 'annex-d', 'annex-e', 'annex-h', 'annex-j', 'annex-k'] as $slug) {
        $this->actingAs($user)
            ->get("/annex/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LegacyForms/Show')
                ->where('form.slug', $slug));
    }
});

it('404s unknown annex slugs', function (): void {
    $this->actingAs(User::factory()->employee()->withPageAccess()->create())
        ->get('/annex/annex-z')
        ->assertNotFound();
});

it('reads annex D and E from the OPCR target register', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
    opcrRow(['strategic_goal' => 'Better outcomes']);

    $this->actingAs($user)
        ->get('/annex/annex-d')
        ->assertInertia(fn ($page) => $page->has('rows', 1));

    $this->actingAs($user)
        ->get('/annex/annex-e')
        ->assertInertia(fn ($page) => $page->has('rows', 1));
});

it('downloads an annex as CSV', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
    opcrRow();

    $this->actingAs($user)
        ->get('/annex/annex-d/download')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('restricts the OPCR module to admins', function (): void {
    opcrRow();

    $this->actingAs(User::factory()->employee()->withPageAccess()->create())->get('/opcr')->assertForbidden();
    $this->actingAs(User::factory()->focal()->withPageAccess()->create())->get('/opcr')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/opcr')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('LegacyForms/Opcr')
            ->has('rows', 1));
});

it('lets admins add, update, and delete OPCR targets', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/opcr', [
            'success_indicator' => 'Publish research outputs',
            'division_accountable' => 'Research',
        ])
        ->assertRedirect();

    $row = DB::table('performance_targets')->first();
    expect($row)->not->toBeNull();

    $this->actingAs($admin)
        ->put("/opcr/{$row->id}", [
            'success_indicator' => 'Publish research outputs',
            'division_accountable' => 'Research',
            'annual_target' => '3 outputs',
        ])
        ->assertRedirect();

    expect(DB::table('performance_targets')->where('id', $row->id)->value('annual_target'))->toBe('3 outputs');

    $this->actingAs($admin)
        ->delete("/opcr/{$row->id}")
        ->assertRedirect();

    expect(DB::table('performance_targets')->where('id', $row->id)->exists())->toBeFalse();
});

it('requires the success indicator and accountable division on OPCR targets', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/opcr', ['annual_target' => '5%'])
        ->assertSessionHasErrors(['success_indicator', 'division_accountable']);
});

it('exports the OPCR register as CSV', function (): void {
    opcrRow();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/opcr/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('denies employees adding rows to editable annex workspaces', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/annex/annex-b', ['values' => ['Financial', 'Grow services', 'Revenue index', 'Finance']])
        ->assertForbidden();
});

it('lets admins and focals manage editable annex workspace rows', function (): void {
    $focal = User::factory()->focal()->withPageAccess()->create();

    $this->actingAs($focal)
        ->post('/annex/annex-b', ['values' => ['Financial', 'Grow services', 'Revenue index', 'Finance']])
        ->assertRedirect();

    $row = DB::table('annex_workspace_rows')->where('slug', 'annex-b')->first();
    expect($row)->not->toBeNull()
        ->and($row->created_by)->toBe($focal->id)
        ->and(json_decode($row->data, true)['Perspective'])->toBe('Financial');

    $this->actingAs($focal)
        ->put("/annex/annex-b/{$row->id}", ['values' => ['Customer', 'Better access', 'Wait time', 'Clinical']])
        ->assertRedirect();

    expect(json_decode(DB::table('annex_workspace_rows')->where('id', $row->id)->value('data'), true)['Perspective'])
        ->toBe('Customer');

    $this->actingAs(User::factory()->admin()->create())
        ->delete("/annex/annex-b/{$row->id}")
        ->assertRedirect();

    expect(DB::table('annex_workspace_rows')->where('id', $row->id)->exists())->toBeFalse();
});

it('refuses to edit annexes derived from the OPCR register', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/annex/annex-d', ['values' => ['a', 'b', 'c', 'd']])
        ->assertStatus(422);
});

it('validates annex workspace row shape', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/annex/annex-b', ['values' => ['only one']])
        ->assertSessionHasErrors('values');
});
