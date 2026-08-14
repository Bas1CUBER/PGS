<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

function operationsReviewRow(int $employeeId, array $data = ['department' => 'Clinical']): int
{
    return DB::table('operations_review')->insertGetId([
        'employee_id' => $employeeId,
        'form_data' => json_encode($data),
        'created_at' => now(),
    ]);
}

it('requires authentication for operations review pages', function (): void {
    $this->get('/operations-review')->assertRedirect('/login');
});

it('shows only the employees own reviews to employees', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $other = User::factory()->employee()->withPageAccess()->create();
    operationsReviewRow($employee->id);
    operationsReviewRow($other->id);

    $this->actingAs($employee)
        ->get('/operations-review')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OperationsReview/Index')
            ->has('reviews', 1));
});

it('shows all reviews to focals and admins', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    operationsReviewRow($employee->id);
    operationsReviewRow($employee->id);

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->get('/operations-review')
        ->assertInertia(fn ($page) => $page->has('reviews', 2));

    $this->actingAs(User::factory()->admin()->create())
        ->get('/operations-review')
        ->assertInertia(fn ($page) => $page->has('reviews', 2));
});

it('stores a new operations review for the employee', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/operations-review', [
            'department' => 'Clinical Services',
            'head_deputy' => 'Dr. Reyes',
            'documenter' => 'A. Cruz',
        ])
        ->assertRedirect();

    $row = DB::table('operations_review')->first();

    expect($row)->not->toBeNull()
        ->and($row->employee_id)->toBe($employee->id)
        ->and(json_decode($row->form_data, true)['department'])->toBe('Clinical Services');
});

it('requires the mandatory operations review fields', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();

    $this->actingAs($employee)
        ->post('/operations-review', ['department' => 'Only department'])
        ->assertSessionHasErrors(['head_deputy', 'documenter']);
});

it('exports the own operations review as PDF', function (): void {
    $employee = User::factory()->employee()->withPageAccess()->create();
    $id = operationsReviewRow($employee->id);

    $this->actingAs($employee)
        ->get("/operations-review/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('does not export another employees operations review', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $intruder = User::factory()->employee()->withPageAccess()->create();
    $id = operationsReviewRow($owner->id);

    $this->actingAs($intruder)
        ->get("/operations-review/{$id}/pdf")
        ->assertForbidden();
});

it('lets focals export any operations review', function (): void {
    $owner = User::factory()->employee()->withPageAccess()->create();
    $id = operationsReviewRow($owner->id);

    $this->actingAs(User::factory()->focal()->withPageAccess()->create())
        ->get("/operations-review/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
