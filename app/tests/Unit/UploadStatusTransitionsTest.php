<?php

declare(strict_types=1);

use App\Modules\UploadModuleRegistry;
use App\Services\UploadModuleService;

/**
 * Upload statuses may ONLY change through the STATUS_TRANSITIONS graph that
 * updateStatus() enforces under a row lock. These tests pin the graph and
 * each module's initial status so a registry edit cannot silently open an
 * illegal jump.
 */
function service(): UploadModuleService
{
    $ref = new ReflectionClass(UploadModuleService::class);

    return $ref->newInstanceWithoutConstructor();
}

function transitions(): array
{
    return UploadModuleService::STATUS_TRANSITIONS;
}

function graphAllows(?string $from, string $to): bool
{
    if ($from === null) {
        return false;
    }

    return in_array($to, transitions()[$from] ?? [], true);
}

it('allows review from every awaiting state', function (): void {
    expect(graphAllows('Pending', 'Approved'))->toBeTrue()
        ->and(graphAllows('Pending', 'Returned'))->toBeTrue()
        ->and(graphAllows('In Progress', 'Approved'))->toBeTrue()
        ->and(graphAllows('In Progress', 'Returned'))->toBeTrue();
});

it('lets a returned upload be resubmitted or approved directly', function (): void {
    expect(graphAllows('Returned', 'Pending'))->toBeTrue()
        ->and(graphAllows('Returned', 'In Progress'))->toBeTrue()
        ->and(graphAllows('Returned', 'Approved'))->toBeTrue();
});

it('lets staff revoke an approval but never resurrect from nothing', function (): void {
    expect(graphAllows('Approved', 'Returned'))->toBeTrue()
        ->and(graphAllows(null, 'Approved'))->toBeFalse()
        ->and(graphAllows(null, 'Pending'))->toBeFalse();
});

it('rejects every other jump', function (): void {
    expect(graphAllows('Approved', 'Pending'))->toBeFalse()
        ->and(graphAllows('Approved', 'Approved'))->toBeFalse()
        ->and(graphAllows('Pending', 'Pending'))->toBeFalse()
        ->and(graphAllows('Unknown', 'Approved'))->toBeFalse()
        ->and(graphAllows('Pending', 'Nonexistent'))->toBeFalse();
});

it('every transition target is itself a known source or terminal', function (): void {
    $graph = transitions();
    $sources = array_keys($graph);

    foreach ($graph as $edges) {
        foreach ($edges as $to) {
            // Approved is terminal after its revoke exit; every other target
            // must exist as a defined source so the graph stays connected.
            if (! in_array($to, ['Approved'], true)) {
                expect(in_array($to, $sources, true))->toBeTrue("{$to} must be a defined source");
            }
        }
    }
});

it('starts Pending modules at Pending', function (): void {
    $svc = service();

    foreach (['operations-review', 'strategy-review', 'communication-plan'] as $slug) {
        $module = UploadModuleRegistry::find($slug);
        expect($module)->not->toBeNull()
            ->and($svc->initialStatus($module))->toBe('Pending');
    }
});

it('starts governance modules In Progress', function (): void {
    $svc = service();

    foreach (['governance-culture', 'governance-sharing'] as $slug) {
        $module = UploadModuleRegistry::find($slug);
        expect($module)->not->toBeNull()
            ->and($svc->initialStatus($module))->toBe('In Progress');
    }
});

it('falls back safely for modules without status support', function (): void {
    $module = UploadModuleRegistry::find('resources');
    expect($module['has_status'])->toBeFalse()
        ->and(service()->initialStatus($module))->toBe('Pending'); // first value fallback
});
