<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

require __DIR__.'/auth.php';
