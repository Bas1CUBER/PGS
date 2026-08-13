<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SurveyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->userOrFail($request);

        $surveys = DB::table('surveys as s')
            ->leftJoin('surveys_done as d', function (JoinClause $join) use ($user): void {
                $join->on('d.survey_id', '=', 's.id')->where('d.user_id', '=', $user->id);
            })
            ->select('s.id', 's.title', 's.url', 's.status', 's.created_at', DB::raw('CASE WHEN d.survey_id IS NOT NULL THEN 1 ELSE 0 END as done'))
            ->whereNull('s.archived_at')
            ->orderByDesc('s.created_at')
            ->get();

        return Inertia::render('Surveys/Index', [
            'surveys' => $surveys,
        ]);
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
}
