<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function strategyReviewRow(int $employeeId, string $status = 'Draft', array $data = ['objective' => 'Improve services']): int
{
    return DB::table('strategy_review_forms')->insertGetId([
        'employee_id' => $employeeId,
        'form_data' => json_encode($data),
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('requires authentication for strategy review pages', function (): void {
    $this->get('/strategy-review')->assertRedirect('/login');
});

it('shows only the employees own forms to employees', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $other = User::factory()->employee()->withPageAccess()->create();
    strategyReviewRow($employee->id, 'Draft');
    strategyReviewRow($other->id, 'Submitted');

    $this->actingAs($employee)
        ->get('/strategy-review')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StrategyReview/Index')
            ->has('forms', 1)
            ->where('forms.0.status', 'Draft'));
});

it('shows all forms to focals and admins', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    strategyReviewRow($employee->id, 'Draft');
    strategyReviewRow($employee->id, 'Submitted');

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->get('/strategy-review')
        ->assertInertia(fn ($page) => $page->has('forms', 2));

    $this->actingAs(User::factory()->admin()->create())
        ->get('/strategy-review')
        ->assertInertia(fn ($page) => $page->has('forms', 2));
});

it('stores a new strategy review for the employee', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/strategy-review', [
            'objective' => 'Expand services',
            'status' => 'Submitted',
        ])
        ->assertRedirect();

    $row = DB::table('strategy_review_forms')->first();

    expect($row)->not->toBeNull()
        ->and($row->employee_id)->toBe($employee->id)
        ->and($row->status)->toBe('Submitted')
        ->and(json_decode($row->form_data, true)['objective'])->toBe('Expand services');
});

it('defaults a stored review to draft', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/strategy-review', ['objective' => 'Draft work'])
        ->assertRedirect();

    expect(DB::table('strategy_review_forms')->value('status'))->toBe('Draft');
});

it('allows the owner to update their form', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id);

    $this->actingAs($employee)
        ->put("/strategy-review/{$id}", ['objective' => 'Updated objective'])
        ->assertRedirect();

    expect(json_decode(DB::table('strategy_review_forms')->where('id', $id)->value('form_data'), true)['objective'])
        ->toBe('Updated objective');
});

it('denies employees updating another employees form', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $intruder = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($owner->id);

    $this->actingAs($intruder)
        ->put("/strategy-review/{$id}", ['objective' => 'Hijacked'])
        ->assertForbidden();
});

it('lets admins update any form', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($owner->id);

    $this->actingAs(User::factory()->admin()->create())
        ->put("/strategy-review/{$id}", ['objective' => 'Admin edit'])
        ->assertRedirect();

    expect(json_decode(DB::table('strategy_review_forms')->where('id', $id)->value('form_data'), true)['objective'])
        ->toBe('Admin edit');
});

it('denies employees reviewing forms', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id, 'Submitted');

    $this->actingAs($employee)
        ->post("/strategy-review/{$id}/review", ['status' => 'Approved'])
        ->assertForbidden();
});

it('lets focals approve or return a submitted form', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id, 'Submitted');
    $focal = User::factory()->focal()->withPageAccess()->create();

    $this->actingAs($focal)
        ->post("/strategy-review/{$id}/review", ['status' => 'Returned'])
        ->assertRedirect();

    expect(DB::table('strategy_review_forms')->where('id', $id)->value('status'))->toBe('Returned')
        ->and(AuditLog::query()
            ->where('action', 'strategy_review_forms.status_changed')
            ->where('resource_id', (string) $id)
            ->exists())->toBeTrue();
});

it('finalizes approved forms so they cannot be re-decided', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id, 'Submitted');

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->post("/strategy-review/{$id}/review", ['status' => 'Approved'])
        ->assertRedirect();

    expect(DB::table('strategy_review_forms')->where('id', $id)->value('status'))->toBe('Approved');

    // A second reviewer decision on an already-approved form must fail.
    $this->actingAs(User::factory()->admin()->withPageAccess()->create())
        ->post("/strategy-review/{$id}/review", ['status' => 'Returned'])
        ->assertForbidden();
});

it('lets owners resubmit a returned form through the workflow', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id, 'Returned');

    $this->actingAs($employee)
        ->put("/strategy-review/{$id}", ['objective' => 'Fixed objective', 'status' => 'Submitted'])
        ->assertRedirect();

    expect(DB::table('strategy_review_forms')->where('id', $id)->value('status'))->toBe('Submitted');
});

it('denies reverting an approved form back to draft', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($owner->id, 'Approved');

    $this->actingAs(User::factory()->admin()->create())
        ->put("/strategy-review/{$id}", ['objective' => 'Rewritten'])
        ->assertForbidden();

    expect(DB::table('strategy_review_forms')->where('id', $id)->value('status'))->toBe('Approved');
});

it('rejects invalid review decisions', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id, 'Submitted');

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->post("/strategy-review/{$id}/review", ['status' => 'Maybe'])
        ->assertSessionHasErrors('status');
});

it('exports the own strategy review as PDF', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($employee->id);

    $this->actingAs($employee)
        ->get("/strategy-review/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('does not export another employees strategy review', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $intruder = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($owner->id);

    $this->actingAs($intruder)
        ->get("/strategy-review/{$id}/pdf")
        ->assertForbidden();
});

it('lets focals export any strategy review', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $id = strategyReviewRow($owner->id);

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->get("/strategy-review/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
