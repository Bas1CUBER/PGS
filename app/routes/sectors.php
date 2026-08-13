<?php

declare(strict_types=1);

use App\Http\Controllers\SectorModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware('auth')->group(function (): void {
        Route::get('/sectors', [SectorModuleController::class, 'index'])->name('sectors.index');
        Route::get('/sectors/{slug}', [SectorModuleController::class, 'show'])->name('sectors.show');
        Route::put('/sectors/{slug}/rows/{id}', [SectorModuleController::class, 'updateRow'])->name('sectors.rows.update');
        Route::put('/sectors/{slug}/progress/{id}', [SectorModuleController::class, 'updateProgress'])->name('sectors.progress.update');
    });
});
