<?php

declare(strict_types=1);

use App\Enums\DeliverableStatus;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('requires authentication for deliverables', function (): void {
    $this->get('/deliverables')->assertRedirect('/login');
});

it('lists deliverables for any authenticated user', function (): void {
    $user = User::factory()->employee()->create();
    Deliverable::query()->create([
        'title' => 'Annual Report',
        'status' => DeliverableStatus::Ongoing->value,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/deliverables')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Deliverables/Index')
            ->has('deliverables.data', 1));
});

it('scopes the list to own deliverables for employees', function (): void {
    $employee = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    Deliverable::query()->create(['title' => 'Mine', 'status' => DeliverableStatus::Ongoing->value, 'uploaded_by' => $employee->id]);
    Deliverable::query()->create(['title' => 'Not mine', 'status' => DeliverableStatus::Ongoing->value, 'uploaded_by' => $other->id]);

    $this->actingAs($employee)
        ->get('/deliverables')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('deliverables.data', 1));
});

it('creates a deliverable with an uploaded mov file', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->post('/deliverables', [
            'title' => 'Quarterly Target',
            'division' => 'ICT Division',
            'target_date' => now()->addMonth()->format('Y-m-d'),
            'status' => DeliverableStatus::NotYetStarted->value,
            'mov_file' => UploadedFile::fake()->create('mov.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect('/deliverables');

    $deliverable = Deliverable::query()->where('title', 'Quarterly Target')->first();
    expect($deliverable)->not->toBeNull()
        ->and($deliverable->uploaded_by)->toBe($user->id)
        ->and($deliverable->mov_file)->not->toBeNull()
        ->and(Storage::disk('local')->exists((string) $deliverable->mov_file))->toBeTrue();

    expect(AuditLog::query()->where('action', 'deliverable.created')->exists())->toBeTrue();
});

it('updates a deliverable', function (): void {
    $user = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Old title',
        'status' => DeliverableStatus::Ongoing->value,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->put("/deliverables/{$deliverable->id}", [
            'title' => 'New title',
            'status' => DeliverableStatus::Accomplished->value,
            'actual_date' => now()->format('Y-m-d'),
        ])
        ->assertRedirect('/deliverables');

    expect($deliverable->fresh()->title)->toBe('New title')
        ->and($deliverable->fresh()->status)->toBe(DeliverableStatus::Ongoing);
});

it('denies updates to other employees deliverables', function (): void {
    $owner = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Owned',
        'status' => DeliverableStatus::Ongoing->value,
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($other)
        ->put("/deliverables/{$deliverable->id}", [
            'title' => 'Hacked',
            'status' => DeliverableStatus::Ongoing->value,
        ])
        ->assertForbidden();
});

it('transitions status through the workflow endpoint', function (): void {
    $user = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::NotYetStarted->value,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post("/deliverables/{$deliverable->id}/status", [
            'to' => DeliverableStatus::Ongoing->value,
        ])
        ->assertRedirect();

    expect($deliverable->fresh()->status)->toBe(DeliverableStatus::Ongoing);
});

it('rejects illegal transitions via the endpoint', function (): void {
    $user = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::Accomplished->value,
        'uploaded_by' => $user->id,
    ]);

    // Employee cannot reopen an accomplished deliverable (admin/focal only).
    $this->actingAs($user)
        ->post("/deliverables/{$deliverable->id}/status", [
            'to' => DeliverableStatus::Ongoing->value,
        ])
        ->assertForbidden();
});

it('deletes a deliverable and its mov file', function (): void {
    $user = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::Ongoing->value,
        'uploaded_by' => $user->id,
        'mov_file' => 'deliverables/example.pdf',
    ]);
    Storage::disk('local')->put('deliverables/example.pdf', 'data');

    $this->actingAs($user)
        ->delete("/deliverables/{$deliverable->id}")
        ->assertRedirect('/deliverables');

    expect(Deliverable::query()->find($deliverable->id))->toBeNull()
        ->and(Storage::disk('local')->exists('deliverables/example.pdf'))->toBeFalse();
});
