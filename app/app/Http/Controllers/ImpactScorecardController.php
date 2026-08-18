<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
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
        $measures = DB::table('impact_scorecard_measures')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $years = DB::table('impact_scorecard_years')
            ->orderBy('sort_order')
            ->orderBy('year')
            ->get();

        $values = collect(DB::table('impact_scorecard_values')->get())
            ->map(static fn (object $v): array => (array) $v)
            ->keyBy(fn (array $v): string => $this->toStr($v['measure_id'] ?? null).':'.$this->toStr($v['year_id'] ?? null));

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

        $max = (int) DB::table('impact_scorecard_measures')->max('sort_order');

        $id = DB::table('impact_scorecard_measures')->insertGetId([
            'impact' => $request->string('impact')->toString(),
            'measure' => $request->string('measure')->toString(),
            'bl' => $request->filled('bl') ? $request->string('bl')->toString() : null,
            'sort_order' => $max + 1,
        ]);

        $this->audit->record(
            $this->userId($request),
            'scorecard.measure_created',
            'impact_scorecard_measures',
            (string) $id,
            request: $request,
        );

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

        DB::table('impact_scorecard_measures')->where('id', $measure)->update([
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

        return back()->with('success', 'Measure updated.');
    }

    public function destroyMeasure(Request $request, int $measure): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        DB::transaction(function () use ($measure): void {
            DB::table('impact_scorecard_values')->where('measure_id', $measure)->delete();
            DB::table('impact_scorecard_measures')->where('id', $measure)->delete();
        });

        $this->audit->record(
            $this->userId($request),
            'scorecard.measure_deleted',
            'impact_scorecard_measures',
            (string) $measure,
            request: $request,
        );

        return back()->with('success', 'Measure deleted.');
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])->validate();

        $year = $request->integer('year');

        if (DB::table('impact_scorecard_years')->where('year', $year)->exists()) {
            return back()->with('error', 'That year already exists.');
        }

        $max = (int) DB::table('impact_scorecard_years')->max('sort_order');

        $id = DB::table('impact_scorecard_years')->insertGetId([
            'year' => $year,
            'sort_order' => $max + 1,
        ]);

        $this->audit->record(
            $this->userId($request),
            'scorecard.year_created',
            'impact_scorecard_years',
            (string) $id,
            request: $request,
        );

        return back()->with('success', 'Year added.');
    }

    public function destroyYear(Request $request, int $year): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        DB::transaction(function () use ($year): void {
            DB::table('impact_scorecard_values')->where('year_id', $year)->delete();
            DB::table('impact_scorecard_years')->where('id', $year)->delete();
        });

        $this->audit->record(
            $this->userId($request),
            'scorecard.year_deleted',
            'impact_scorecard_years',
            (string) $year,
            request: $request,
        );

        return back()->with('success', 'Year removed.');
    }

    public function updateValue(Request $request, int $measure, int $year): RedirectResponse
    {
        $this->assertAdminOrFocal($request);

        Validator::make($request->all(), [
            'value' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $value = $request->filled('value') ? $request->string('value')->toString() : null;

        DB::table('impact_scorecard_values')->updateOrInsert(
            ['measure_id' => $measure, 'year_id' => $year],
            ['value' => $value, 'updated_at' => now()],
        );

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

    private function toStr(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    private function assertAdminOrFocal(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->isAdmin() || $user->isFocal()), 403);
    }
}
