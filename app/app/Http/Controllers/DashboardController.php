<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $payload = Cache::remember(
            "pgs_dashboard_{$user->role->value}_{$user->id}",
            60,
            fn (): array => $this->dashboard->for($user),
        );

        return Inertia::render('Dashboard', [
            'dashboard' => $payload,
        ]);
    }
}
