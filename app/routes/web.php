<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\LegacyRedirectMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

if (app()->environment(['local', 'testing'])) {
    // Middleware test fixtures (never registered in production).
    Route::prefix('/role-check')->middleware('auth')->group(function (): void {
        Route::get('/admin-only', fn (): string => 'ok')->middleware('role:admin');
        Route::get('/focal', fn (): string => 'ok')->middleware('role:admin,focal');
    });

    Route::prefix('/access-check')->middleware('auth')->group(function (): void {
        foreach (['roadmaps', 'scorecard', 'performance_assessment', 'cascading', 'governance'] as $module) {
            Route::get("/{$module}", fn (): string => 'ok')->middleware("page.access:{$module}");
        }
    });
}

// Legacy URL redirects for unmatched paths (bookmarks from the old app).
Route::fallback(function (Request $request) {
    $target = LegacyRedirectMiddleware::targetFor('/'.ltrim($request->path(), '/'));

    if ($target !== null) {
        return redirect($target, 301);
    }

    abort(404);
});

require __DIR__.'/auth.php';
