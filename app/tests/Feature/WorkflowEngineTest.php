<?php

declare(strict_types=1);

use App\Enums\DeliverableStatus;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\User;
use App\Services\TransitionsWorkflowService;
use App\Services\WorkflowRegistry;
use Illuminate\Auth\Access\AuthorizationException;

it('allows an owner to move a deliverable from not-started to ongoing', function (): void {
    $owner = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::NotYetStarted->value,
        'uploaded_by' => $owner->id,
    ]);

    $workflow = new TransitionsWorkflowService(Deliverable::class, [
        DeliverableStatus::NotYetStarted->value => [
            ['to' => DeliverableStatus::Ongoing->value, 'actor' => '*'],
        ],
    ]);

    $result = $workflow->transition($deliverable, DeliverableStatus::Ongoing->value, $owner);

    expect($result->fresh()->status)->toBe(DeliverableStatus::Ongoing);
});

it('denies an illegal transition with 403 semantics', function (): void {
    $owner = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::Accomplished->value,
        'uploaded_by' => $owner->id,
    ]);

    $workflow = new TransitionsWorkflowService(Deliverable::class, [
        DeliverableStatus::NotYetStarted->value => [
            ['to' => DeliverableStatus::Ongoing->value, 'actor' => '*'],
        ],
    ]);

    $workflow->transition($deliverable, DeliverableStatus::Ongoing->value, $owner);
})->throws(AuthorizationException::class);

it('denies transitions by wrong actor roles', function (): void {
    $employee = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::Accomplished->value,
        'uploaded_by' => $employee->id,
    ]);

    $workflow = new TransitionsWorkflowService(Deliverable::class, [
        DeliverableStatus::Accomplished->value => [
            ['to' => DeliverableStatus::Ongoing->value, 'actor' => 'admin|focal'],
        ],
    ]);

    $workflow->transition($deliverable, DeliverableStatus::Ongoing->value, $employee);
})->throws(AuthorizationException::class);

it('writes an audit log entry for every transition', function (): void {
    $owner = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::NotYetStarted->value,
        'uploaded_by' => $owner->id,
    ]);

    $workflow = new TransitionsWorkflowService(Deliverable::class, [
        DeliverableStatus::NotYetStarted->value => [
            ['to' => DeliverableStatus::Ongoing->value, 'actor' => '*'],
        ],
    ]);

    $workflow->transition($deliverable, DeliverableStatus::Ongoing->value, $owner);

    expect(AuditLog::query()
        ->where('action', 'p_deliverables.status_changed')
        ->where('resource_id', (string) $deliverable->id)
        ->exists())->toBeTrue();
});

it('reports canTransition accurately', function (): void {
    $employee = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::Ongoing->value,
        'uploaded_by' => $employee->id,
    ]);

    $workflow = new TransitionsWorkflowService(Deliverable::class, [
        DeliverableStatus::Ongoing->value => [
            ['to' => DeliverableStatus::Accomplished->value, 'actor' => '*'],
        ],
    ]);

    expect($workflow->canTransition($deliverable, DeliverableStatus::Accomplished->value, $employee))->toBeTrue()
        ->and($workflow->canTransition($deliverable, DeliverableStatus::NotYetStarted->value, $employee))->toBeFalse();
});

// ── Production map (WorkflowRegistry) end-to-end ─────────────────────────────

it('exercises the production deliverable map: employee cannot skip straight to accomplished', function (): void {
    $owner = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::NotYetStarted->value,
        'uploaded_by' => $owner->id,
    ]);

    // NotYetStarted -> Accomplished is admin|focal only in production.
    WorkflowRegistry::deliverables()->transition($deliverable, DeliverableStatus::Accomplished->value, $owner);
})->throws(AuthorizationException::class);

it('exercises the production deliverable map: admin may reset an accomplished deliverable', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::Accomplished->value,
        'uploaded_by' => $owner->id,
    ]);

    WorkflowRegistry::deliverables()->transition($deliverable, DeliverableStatus::Ongoing->value, $admin);

    expect($deliverable->fresh()->status)->toBe(DeliverableStatus::Ongoing);
});

it('writes exactly one audit entry per production-map transition', function (): void {
    $owner = User::factory()->employee()->create();
    $deliverable = Deliverable::query()->create([
        'title' => 'Task',
        'status' => DeliverableStatus::NotYetStarted->value,
        'uploaded_by' => $owner->id,
    ]);

    WorkflowRegistry::deliverables()->transition($deliverable, DeliverableStatus::Ongoing->value, $owner);

    expect(AuditLog::query()
        ->where('action', 'p_deliverables.status_changed')
        ->where('resource_id', (string) $deliverable->id)
        ->count())->toBe(1);
});
