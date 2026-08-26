<?php

declare(strict_types=1);

use App\Enums\DeliverableStatus;
use App\Services\TransitionsWorkflowService;
use App\Services\WorkflowRegistry;

/**
 * The transition maps are the single source of truth for every status
 * change (docs/Workflows.md). These pure tests pin the graph so an
 * accidental edit fails fast instead of silently widening who can move what.
 */
function registryTransitions(TransitionsWorkflowService $workflow): array
{
    $ref = new ReflectionProperty($workflow, 'transitions');
    $ref->setAccessible(true);

    /** @var array<string, list<array{to: string, actor: string}>> */
    return $ref->getValue($workflow);
}

function mapAllows(array $transitions, string $from, string $to, string $role): bool
{
    foreach ($transitions[$from] ?? [] as $transition) {
        if ($transition['to'] !== $to) {
            continue;
        }
        if ($transition['actor'] === '*' || in_array($role, explode('|', $transition['actor']), true)) {
            return true;
        }
    }

    return false;
}

it('defines every deliverable progress transition', function (): void {
    $transitions = registryTransitions(WorkflowRegistry::deliverables());

    expect(array_keys($transitions))->toEqual([
        DeliverableStatus::NotYetStarted->value,
        DeliverableStatus::Ongoing->value,
        DeliverableStatus::Accomplished->value,
    ]);
});

it('lets any user move a deliverable forward one step', function (): void {
    $t = registryTransitions(WorkflowRegistry::deliverables());

    expect(mapAllows($t, 'Not Yet Started', 'Ongoing', 'employee'))->toBeTrue()
        ->and(mapAllows($t, 'Not Yet Started', 'Ongoing', 'focal'))->toBeTrue()
        ->and(mapAllows($t, 'Ongoing', 'Accomplished', 'employee'))->toBeTrue();
});

it('restricts deliverable regression and finalization jumps to staff', function (): void {
    $t = registryTransitions(WorkflowRegistry::deliverables());

    expect(mapAllows($t, 'Not Yet Started', 'Accomplished', 'employee'))->toBeFalse()
        ->and(mapAllows($t, 'Not Yet Started', 'Accomplished', 'admin'))->toBeTrue()
        ->and(mapAllows($t, 'Not Yet Started', 'Accomplished', 'focal'))->toBeTrue()
        ->and(mapAllows($t, 'Ongoing', 'Not Yet Started', 'employee'))->toBeFalse()
        ->and(mapAllows($t, 'Ongoing', 'Not Yet Started', 'admin'))->toBeTrue();
});

it('only allows staff to reopen an accomplished deliverable', function (): void {
    $t = registryTransitions(WorkflowRegistry::deliverables());

    expect(mapAllows($t, 'Accomplished', 'Ongoing', 'employee'))->toBeFalse()
        ->and(mapAllows($t, 'Accomplished', 'Ongoing', 'admin'))->toBeTrue()
        ->and(mapAllows($t, 'Accomplished', 'Ongoing', 'focal'))->toBeTrue();
});

it('has no illegal self-transitions in the deliverable graph', function (): void {
    $t = registryTransitions(WorkflowRegistry::deliverables());

    foreach ($t as $from => $edges) {
        foreach ($edges as $edge) {
            expect($edge['to'])->not->toBe($from);
        }
    }
});

it('defines the strategy review lifecycle submit → decide → revise', function (): void {
    $t = registryTransitions(WorkflowRegistry::strategyReviewForms());

    expect(array_keys($t))->toEqual(['Draft', 'Submitted', 'Returned'])
        ->and(mapAllows($t, 'Draft', 'Submitted', 'employee'))->toBeTrue()
        ->and(mapAllows($t, 'Submitted', 'Approved', 'admin'))->toBeTrue()
        ->and(mapAllows($t, 'Submitted', 'Approved', 'focal'))->toBeTrue()
        ->and(mapAllows($t, 'Submitted', 'Returned', 'admin'))->toBeTrue()
        ->and(mapAllows($t, 'Returned', 'Submitted', 'employee'))->toBeTrue()
        ->and(mapAllows($t, 'Returned', 'Draft', 'employee'))->toBeTrue();
});

it('blocks employees from deciding strategy reviews', function (): void {
    $t = registryTransitions(WorkflowRegistry::strategyReviewForms());

    expect(mapAllows($t, 'Submitted', 'Approved', 'employee'))->toBeFalse()
        ->and(mapAllows($t, 'Submitted', 'Returned', 'employee'))->toBeFalse();
});

it('makes Approved a terminal state with no exits', function (): void {
    $t = registryTransitions(WorkflowRegistry::strategyReviewForms());

    expect($t)->not->toHaveKey('Approved')
        ->and(mapAllows($t, 'Approved', 'Returned', 'admin'))->toBeFalse()
        ->and(mapAllows($t, 'Approved', 'Draft', 'admin'))->toBeFalse();
});
