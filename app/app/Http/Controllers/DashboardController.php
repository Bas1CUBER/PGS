<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CacheInvalidationService;
use App\Services\DashboardService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
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

        $payload = CacheInvalidationService::remember(
            'dashboard',
            "{$user->role->value}:{$user->id}",
            fn (): array => $this->dashboard->for($user),
            60,
        );

        return Inertia::render('Dashboard', [
            'dashboard' => $payload,
        ]);
    }
}
