<?php

declare(strict_types=1);

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DeadlineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])->group(function (): void {
    Route::get('/deadlines', [DeadlineController::class, 'index'])->name('deadlines.index');
    Route::put('/deadlines/{role}', [DeadlineController::class, 'update'])->name('deadlines.update');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
    Route::get('/backups/{disk}/{path}', [BackupController::class, 'download'])
        ->name('backups.download')
        ->where('path', '.*');
    Route::delete('/backups/{disk}/{path}', [BackupController::class, 'destroy'])
        ->name('backups.destroy')
        ->where('path', '.*');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
