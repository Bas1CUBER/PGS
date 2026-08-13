<?php

declare(strict_types=1);

use App\Models\DeadlineControl;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('requires authentication for the dashboard', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows admin dashboard aggregates', function (): void {
    $admin = User::factory()->admin()->create();
    DB::table('p_deliverables')->insert([
        'title' => 'Deliverable A',
        'status' => 'Ongoing',
        'uploaded_by' => $admin->id,
    ]);
    Notice::query()->create(['title' => 'Notice A']);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('dashboard.stats')
            ->where('dashboard.stats.deliverables_total', 1)
            ->where('dashboard.stats.notices_total', 1)
            ->where('dashboard.stats.users_total', 1)
            ->has('dashboard.recent_uploads'));
});

it('shows employee dashboard scoped to their own deliverables', function (): void {
    $employee = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    DB::table('p_deliverables')->insert([
        ['title' => 'Mine', 'status' => 'Accomplished', 'uploaded_by' => $employee->id],
        ['title' => 'Not mine', 'status' => 'Ongoing', 'uploaded_by' => $other->id],
    ]);

    $this->actingAs($employee)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.stats.deliverables_total', 1)
            ->where('dashboard.stats.deliverables_accomplished', 1)
            ->has('dashboard.deliverables', 1));
});

it('shows focal dashboard with pending approvals', function (): void {
    $focal = User::factory()->focal()->create();
    $employee = User::factory()->employee()->create();
    DB::table('operations_review_uploads')->insert([
        'employee_id' => $employee->id,
        'original_name' => 'review.pdf',
        'filename' => 'x.pdf',
        'status' => 'Pending',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($focal)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.stats.pending_approvals', 1)
            ->has('dashboard.pending_approvals_list', 1));
});

it('shares the deadline banner state on the dashboard', function (): void {
    $employee = User::factory()->employee()->create();
    DeadlineControl::query()->create([
        'role' => 'employee',
        'enabled' => true,
        'end_time' => now()->addDay(),
        'message' => 'Submit soon.',
    ]);

    $this->actingAs($employee)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('deadline.enabled', true));
});
