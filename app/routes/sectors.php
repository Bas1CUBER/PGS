<?php

declare(strict_types=1);

use App\Http\Controllers\SectorModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified', 'page.access:roadmaps'])->group(function (): void {
        Route::get('/sectors', [SectorModuleController::class, 'index'])->name('sectors.index');
        Route::get('/sectors/{slug}', [SectorModuleController::class, 'show'])->name('sectors.show');
        Route::put('/sectors/{slug}/rows/{id}', [SectorModuleController::class, 'updateRow'])->name('sectors.rows.update');
        Route::post('/sectors/{slug}/rows', [SectorModuleController::class, 'storeRow'])->name('sectors.rows.store');
        Route::delete('/sectors/{slug}/rows/{id}', [SectorModuleController::class, 'destroyRow'])->name('sectors.rows.destroy');
        Route::put('/sectors/{slug}/progress/{id}', [SectorModuleController::class, 'updateProgress'])->name('sectors.progress.update');
        Route::post('/sectors/{slug}/pending/{change}/decision', [SectorModuleController::class, 'decidePending'])->name('sectors.pending.decision');
    });
});
