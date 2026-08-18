<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        $data = CacheInvalidationService::remember('survey', 'index', function () use ($user): array {
            $surveys = DB::table('surveys')
                ->whereNull('archived_at')
                ->orderByDesc('created_at')
                ->get();

            $completionCounts = DB::table('surveys_done')
                ->select('survey_id', DB::raw('COUNT(*) as total'))
                ->groupBy('survey_id')
                ->pluck('total', 'survey_id');

            $surveys = $surveys->map(function (object $survey) use ($completionCounts): array {
                $surveyId = $this->toInt($survey->id);

                return [
                    'id' => $surveyId,
                    'title' => $survey->title,
                    'url' => $survey->url,
                    'status' => $survey->status,
                    'created_at' => $survey->created_at,
                    'completion_count' => $this->toInt($completionCounts[$survey->id] ?? 0),
                ];
            })->values();

            $archived = $this->isAdmin($user)
                ? DB::table('surveys')->whereNotNull('archived_at')->orderByDesc('archived_at')->get()->map(fn (object $survey): array => [
                    'id' => $this->toInt($survey->id),
                    'title' => $survey->title,
                    'url' => $survey->url,
                    'status' => $survey->status,
                    'created_at' => $survey->created_at,
                    'archived_at' => $survey->archived_at,
                    'completion_count' => $this->toInt($completionCounts[$survey->id] ?? 0),
                ])->values()->all()
                : [];

            return ['surveys' => $surveys->all(), 'archived' => $archived];
        }, 60);

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
            'archived' => $data['archived'],
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

        DB::table('surveys')->insert([
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

        $updated = DB::table('surveys')->where('id', $survey)->update([
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

        $updated = DB::table('surveys')->where('id', $survey)->whereNull('archived_at')->update([
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

        $deleted = DB::table('surveys')->where('id', $survey)->whereNotNull('archived_at')->delete();
        abort_unless($deleted > 0, 404);

        CacheInvalidationService::onSurveyChange();

        return back()->with('success', 'Archived survey deleted.');
    }

    public function markDone(Request $request, int $survey): RedirectResponse
    {
        $user = $this->userOrFail($request);

        if (! DB::table('surveys')->where('id', $survey)->exists()) {
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

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
