<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('exports an upload record as PDF', function (): void {
    $user = User::factory()->employee()->create();
    DB::table('operations_review_uploads')->insert([
        'employee_id' => $user->id,
        'filename' => 'x.pdf',
        'original_name' => 'q1-review.pdf',
        'file_size' => 2048,
        'mime_type' => 'application/pdf',
        'status' => 'Pending',
        'uploaded_at' => now(),
    ]);

    $id = DB::table('operations_review_uploads')->first()->id;

    $this->actingAs($user)
        ->get("/uploads/operations-review/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('exports a deliverable as PDF', function (): void {
    $user = User::factory()->employee()->create();
    $id = DB::table('p_deliverables')->insertGetId([
        'title' => 'Annual Report',
        'status' => 'Accomplished',
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get("/deliverables/{$id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('does not export another employee deliverable', function (): void {
    $owner = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $id = DB::table('p_deliverables')->insertGetId([
        'title' => 'Private Report',
        'status' => 'Ongoing',
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($other)
        ->get("/deliverables/{$id}/pdf")
        ->assertForbidden();
});
