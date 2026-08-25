<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\User;
use App\Services\CacheInvalidationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class SurveyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);

        // The cached payload must stay role-agnostic: it is shared between
        // every user, so the archived list (admin-only) is resolved per
        // request below instead of inside the closure.
        $data = CacheInvalidationService::remember('survey', 'index', function (): array {
            $surveys = Survey::query()->active()->orderByDesc('created_at')->get();

            $completionCounts = DB::table('surveys_done')
                ->select('survey_id', DB::raw('COUNT(*) as total'))
                ->groupBy('survey_id')
                ->pluck('total', 'survey_id');

            $surveys = $surveys->map(function (Survey $survey) use ($completionCounts): array {
                return [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'url' => $survey->url,
                    'status' => $survey->status,
                    'created_at' => $survey->created_at,
                    'completion_count' => (int) ($completionCounts[$survey->id] ?? 0),
                ];
            })->values();

            return ['surveys' => $surveys->all()];
        }, 60);

        $archived = [];
        if ($this->isAdmin($user)) {
            $archivedCounts = DB::table('surveys_done')
                ->select('survey_id', DB::raw('COUNT(*) as total'))
                ->groupBy('survey_id')
                ->pluck('total', 'survey_id');

            $archived = Survey::query()->whereNotNull('archived_at')->orderByDesc('archived_at')->get()->map(function (Survey $survey) use ($archivedCounts): array {
                return [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'url' => $survey->url,
                    'status' => $survey->status,
                    'created_at' => $survey->created_at,
                    'archived_at' => $survey->archived_at,
                    'completion_count' => (int) ($archivedCounts[$survey->id] ?? 0),
                ];
            })->values()->all();
        }

        $doneIds = DB::table('surveys_done')
            ->where('user_id', $user->id)
            ->pluck('survey_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $surveys = collect($data['surveys'])->map(function (array $survey) use ($doneIds): array {
            $survey['done'] = in_array($survey['id'], $doneIds, true) ? 1 : 0;

            return $survey;
        })->values();

        return Inertia::render('Surveys/Index', [
            'surveys' => $surveys,
            'archived' => $archived,
            'canManage' => $this->isAdmin($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($this->isAdmin($user), 403);

        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:1024'],
        ])->validate();

        Survey::query()->create([
            'title' => $request->string('title')->toString(),
            'url' => $request->string('url')->toString(),
            'status' => 'Active',
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        CacheInvalidationService::onSurveyChange();

        return back()->with('success', 'Survey published.');
    }

    public function update(Request $request, int $survey): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($this->isAdmin($user), 403);

        Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:1024'],
        ])->validate();

        $updated = Survey::query()->whereKey($survey)->update([
            'title' => $request->string('title')->toString(),
            'url' => $request->string('url')->toString(),
        ]);

        abort_unless($updated > 0, 404);

        CacheInvalidationService::onSurveyChange();

        return back()->with('success', 'Survey updated.');
    }

    public function archive(Request $request, int $survey): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($this->isAdmin($user), 403);

        $updated = Survey::query()->whereKey($survey)->whereNull('archived_at')->update([
            'status' => 'Archived',
            'archived_at' => now(),
        ]);

        abort_unless($updated > 0, 404);

        CacheInvalidationService::onSurveyChange();

        return back()->with('success', 'Survey archived.');
    }

    public function destroy(Request $request, int $survey): RedirectResponse
    {
        $user = $this->userOrFail($request);
        abort_unless($this->isAdmin($user), 403);

        $deleted = Survey::query()->whereKey($survey)->whereNotNull('archived_at')->delete();
        abort_unless($deleted > 0, 404);

        CacheInvalidationService::onSurveyChange();

        return back()->with('success', 'Archived survey deleted.');
    }

    public function markDone(Request $request, int $survey): RedirectResponse
    {
        $user = $this->userOrFail($request);

        if (! Survey::query()->whereKey($survey)->exists()) {
            abort(404);
        }

        DB::table('surveys_done')->updateOrInsert(
            ['survey_id' => $survey, 'user_id' => $user->id],
            ['done_at' => now()],
        );

        CacheInvalidationService::onSurveyChange();

        return back()->with('success', 'Survey marked as done.');
    }

    /**
     * @throws AuthenticationException
     */
    private function userOrFail(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function isAdmin(User $user): bool
    {
        return $user->isAdmin();
    }
}
