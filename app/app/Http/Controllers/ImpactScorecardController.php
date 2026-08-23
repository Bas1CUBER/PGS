<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ImpactScorecardMeasure;
use App\Models\ImpactScorecardValue;
use App\Models\ImpactScorecardYear;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class ImpactScorecardController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        [$measures, $years, $values] = CacheInvalidationService::remember('scorecard', 'index', function (): array {
            $measures = ImpactScorecardMeasure::query()->orderBy('sort_order')->orderBy('id')->get();
            $years = ImpactScorecardYear::query()->orderBy('sort_order')->orderBy('year')->get();
            $values = ImpactScorecardValue::query()->get()->keyBy(
                fn (ImpactScorecardValue $v): string => $v->measure_id.':'.$v->year_id,
            );

            return [$measures, $years, $values];
        }, 60);

        return Inertia::render('Scorecard/Index', [
            'measures' => $measures,
            'years' => $years,
            'values' => $values,
        ]);
    }

    public function storeMeasure(Request $request): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        Validator::make($request->all(), [
            'impact' => ['required', 'string', 'max:255'],
            'measure' => ['required', 'string', 'max:255'],
            'bl' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $max = (int) ImpactScorecardMeasure::query()->max('sort_order');

        $row = ImpactScorecardMeasure::query()->create([
            'impact' => $request->string('impact')->toString(),
            'measure' => $request->string('measure')->toString(),
            'bl' => $request->filled('bl') ? $request->string('bl')->toString() : null,
            'sort_order' => $max + 1,
        ]);

        $this->audit->record(
            $this->userId($request),
            'scorecard.measure_created',
            'impact_scorecard_measures',
            (string) $row->id,
            request: $request,
        );

        CacheInvalidationService::onScorecardChange();

        return back()->with('success', 'Measure added.');
    }

    public function updateMeasure(Request $request, int $measure): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        Validator::make($request->all(), [
            'impact' => ['required', 'string', 'max:255'],
            'measure' => ['required', 'string', 'max:255'],
            'bl' => ['nullable', 'string', 'max:255'],
        ])->validate();

        ImpactScorecardMeasure::query()->whereKey($measure)->update([
            'impact' => $request->string('impact')->toString(),
            'measure' => $request->string('measure')->toString(),
            'bl' => $request->filled('bl') ? $request->string('bl')->toString() : null,
        ]);

        $this->audit->record(
            $this->userId($request),
            'scorecard.measure_updated',
            'impact_scorecard_measures',
            (string) $measure,
            request: $request,
        );

        CacheInvalidationService::onScorecardChange();

        return back()->with('success', 'Measure updated.');
    }

    public function destroyMeasure(Request $request, int $measure): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        DB::transaction(function () use ($measure): void {
            ImpactScorecardValue::query()->where('measure_id', $measure)->delete();
            ImpactScorecardMeasure::query()->whereKey($measure)->delete();
        });

        $this->audit->record(
            $this->userId($request),
            'scorecard.measure_deleted',
            'impact_scorecard_measures',
            (string) $measure,
            request: $request,
        );

        CacheInvalidationService::onScorecardChange();

        return back()->with('success', 'Measure deleted.');
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])->validate();

        $year = $request->integer('year');

        if (ImpactScorecardYear::query()->where('year', $year)->exists()) {
            return back()->with('error', 'That year already exists.');
        }

        $max = (int) ImpactScorecardYear::query()->max('sort_order');

        $row = ImpactScorecardYear::query()->create([
            'year' => $year,
            'sort_order' => $max + 1,
        ]);

        $this->audit->record(
            $this->userId($request),
            'scorecard.year_created',
            'impact_scorecard_years',
            (string) $row->id,
            request: $request,
        );

        CacheInvalidationService::onScorecardChange();

        return back()->with('success', 'Year added.');
    }

    public function destroyYear(Request $request, int $year): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        DB::transaction(function () use ($year): void {
            ImpactScorecardValue::query()->where('year_id', $year)->delete();
            ImpactScorecardYear::query()->whereKey($year)->delete();
        });

        $this->audit->record(
            $this->userId($request),
            'scorecard.year_deleted',
            'impact_scorecard_years',
            (string) $year,
            request: $request,
        );

        CacheInvalidationService::onScorecardChange();

        return back()->with('success', 'Year removed.');
    }

    public function updateValue(Request $request, int $measure, int $year): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        Validator::make($request->all(), [
            'value' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $value = $request->filled('value') ? $request->string('value')->toString() : null;

        ImpactScorecardValue::query()->updateOrInsert(
            ['measure_id' => $measure, 'year_id' => $year],
            ['value' => $value, 'updated_at' => now()],
        );

        CacheInvalidationService::onScorecardChange();

        return back();
    }

    /**
     * @throws AuthenticationException
     */
    private function userId(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return $user->id;
    }

    private function assertAdminOrFocal(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->isAdmin() || $user->isFocal()), 403);
    }
}
