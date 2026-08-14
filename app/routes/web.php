<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\LegacyRedirectMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/guest-assets/{name}', function (string $name) {
    $allowed = [
        'background_image.png',
        'bldg_img1.png',
        'doh_trc_logo.png',
        'final_login.png',
        'final_logo.png',
        'login.png',
        'logo_doh2.png',
        'pgs_logo.png',
        'logo_trc.png',
    ];
    $name = basename(urldecode($name));

    abort_unless(in_array($name, $allowed, true), 404);

    return response()->file(base_path('../img/'.$name));
})->where('name', '[A-Za-z0-9_.-]+')->name('guest-assets');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('/mailbox')->group(function () {
    Route::get('/', [MailboxController::class, 'index'])->name('mailbox.index');
    Route::get('/{mail}', [MailboxController::class, 'show'])->name('mailbox.show');
});

if (app()->environment(['local', 'testing'])) {
    // Middleware test fixtures (never registered in production).
    Route::prefix('/role-check')->middleware('auth')->group(function (): void {
        Route::get('/admin-only', fn (): string => 'ok')->middleware('role:admin');
        Route::get('/focal', fn (): string => 'ok')->middleware('role:admin,focal');
    });

    Route::prefix('/access-check')->middleware('auth')->group(function (): void {
        foreach (['roadmaps', 'scorecard', 'performance_assessment', 'cascading', 'governance'] as $module) {
            Route::get("/{$module}", fn (): string => 'ok')
                ->middleware("page.access:{$module}")
                ->name("access-check.{$module}");
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
