<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

it('shows the communication plan rows', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
    DB::table('communication_plan_roadmap')->insert([
        'objective' => 'Inform staff on the new policy',
        'status' => 'Ongoing',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/communication-plan')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CommPlan/Index')
            ->has('rows', 1)
            ->where('rows.0.objective', 'Inform staff on the new policy'));
});

it('adds and updates a communication plan row as a focal', function (): void {
    $user = User::factory()->focal()->withPageAccess()->create();

    $this->actingAs($user)
        ->post('/communication-plan', [
            'objective' => 'Quarterly town hall',
            'channel' => 'Meeting',
            'timeframe' => 'Q3',
        ])
        ->assertRedirect();

    $row = DB::table('communication_plan_roadmap')->where('objective', 'Quarterly town hall')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('Not Accomplished/Started');

    $this->actingAs($user)
        ->put("/communication-plan/{$row->id}", [
            'objective' => 'Quarterly town hall',
            'channel' => 'Meeting',
            'timeframe' => 'Q3',
            'status' => 'Ongoing',
        ])
        ->assertRedirect();

    expect(DB::table('communication_plan_roadmap')->where('id', $row->id)->value('status'))->toBe('Ongoing');
});

it('forbids employees from authoring communication plan rows', function (): void {
    // Published access matrix: employees are read-only on the plan.
    $employee = User::factory()->employee()->withPageAccess()->create();
    $creator = User::factory()->admin()->create();

    $rowId = DB::table('communication_plan_roadmap')->insertGetId([
        'objective' => 'Manager-owned row',
        'status' => 'Ongoing',
        'created_by' => $creator->id,
    ]);

    $this->actingAs($employee)
        ->post('/communication-plan', ['objective' => 'Employee row'])
        ->assertForbidden();

    $this->actingAs($employee)
        ->put("/communication-plan/{$rowId}", ['objective' => 'Hacked', 'status' => 'Completed'])
        ->assertForbidden();

    $this->actingAs($employee)
        ->delete("/communication-plan/{$rowId}")
        ->assertForbidden();

    expect(DB::table('communication_plan_roadmap')->where('id', $rowId)->value('objective'))->toBe('Manager-owned row')
        ->and(DB::table('communication_plan_roadmap')->where('id', $rowId)->value('status'))->toBe('Ongoing');
});

it('creates albums and uploads photos', function (): void {
    Storage::fake('local');
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->post('/gallery/albums', ['name' => 'Foundation Day'])
        ->assertRedirect();

    $album = DB::table('gallery_albums')->where('name', 'Foundation Day')->first();
    expect($album)->not->toBeNull();

    $this->actingAs($user)
        ->post("/gallery/albums/{$album->id}/photos", [
            'caption' => 'Team photo',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertRedirect();

    expect(DB::table('gallery_photos')->where('album_id', $album->id)->count())->toBe(1);
});

it('shows the gallery with albums', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
    DB::table('gallery_albums')->insert(['name' => 'Album A', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->get('/gallery')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Gallery/Index')
            ->has('albums', 1));
});

it('manages the impact scorecard', function (): void {
    $user = User::factory()->admin()->create();
    DB::table('impact_scorecard_years')->insert(['year' => 2025, 'sort_order' => 1]);

    $this->actingAs($user)
        ->post('/impact-scorecard/measures', [
            'impact' => 'Clinical',
            'measure' => 'Mortality rate',
            'bl' => '10',
        ])
        ->assertRedirect();

    $measure = DB::table('impact_scorecard_measures')->where('measure', 'Mortality rate')->first();
    expect($measure)->not->toBeNull();

    $year = DB::table('impact_scorecard_years')->where('year', 2025)->first();

    $this->actingAs($user)
        ->put("/impact-scorecard/values/{$measure->id}/{$year->id}", ['value' => '8'])
        ->assertRedirect();

    expect(DB::table('impact_scorecard_values')
        ->where('measure_id', $measure->id)
        ->where('year_id', $year->id)
        ->value('value'))->toBe('8');
});

it('lists surveys and marks them done', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();
    DB::table('surveys')->insert(['title' => 'Satisfaction Survey', 'url' => 'https://example.com/survey', 'status' => 'Active', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get('/surveys')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Surveys/Index')
            ->has('surveys', 1)
            ->where('surveys.0.done', 0));

    $survey = DB::table('surveys')->where('title', 'Satisfaction Survey')->first();

    $this->actingAs($user)
        ->post("/surveys/{$survey->id}/done")
        ->assertRedirect();

    expect(DB::table('surveys_done')->where('survey_id', $survey->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('shows and edits sector detail tables', function (): void {
    $user = User::factory()->admin()->create();
    $id = DB::table('resilience_gvr')->insertGetId([
        'indicator' => 'Green areas',
        'share' => '50',
        'y2024' => '45',
    ]);

    $this->actingAs($user)
        ->get('/sectors/resilience/gvr')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SectorDetails/Show')
            ->has('rows.data', 1)
            ->where('breadcrumbs', [
                ['label' => 'Roadmaps', 'href' => '/sectors'],
                ['label' => 'Resilience', 'href' => '/sectors/resilience'],
                ['label' => 'Green Viability Ratio'],
            ]));

    $this->actingAs($user)
        ->put("/sectors/resilience/gvr/{$id}", [
            'indicator' => 'Green areas',
            'share' => '55',
            'y2024' => '48',
        ])
        ->assertRedirect();

    expect(DB::table('resilience_gvr')->where('id', $id)->value('share'))->toBe('55.00');
});

it('404s sector detail pages with mismatched pillar and slug', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get('/sectors/collab/gvr')->assertNotFound();
});

it('301-redirects the pre-nesting sector detail URLs', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/sector-details/collab-relapse-rate')
        ->assertRedirect('/sectors/collab/relapse-rate');
});

it('renders static content pages', function (): void {
    $user = User::factory()->employee()->withPageAccess()->create();

    foreach (['about-strategy-map', 'pgs-core-team', 'multi-sector-governance'] as $slug) {
        $this->actingAs($user)->get("/content/{$slug}")->assertOk();
    }
});

it('redirects the public root to login', function (): void {
    $this->get('/')->assertRedirect('/login');
});
