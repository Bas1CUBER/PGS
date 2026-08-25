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
    // Destructive operations require a fresh password confirmation so a
    // stolen/week-old session cookie cannot wipe or overwrite the database.
    Route::post('/backups', [BackupController::class, 'create'])
        ->name('backups.create')
        ->middleware('password.confirm');
    Route::post('/backups/{disk}/{path}/restore', [BackupController::class, 'restore'])
        ->name('backups.restore')
        ->middleware('password.confirm')
        ->where('path', '.*');
    Route::get('/backups/{disk}/{path}', [BackupController::class, 'download'])
        ->name('backups.download')
        ->where('path', '.*');
    Route::delete('/backups/{disk}/{path}', [BackupController::class, 'destroy'])
        ->name('backups.destroy')
        ->middleware('password.confirm')
        ->where('path', '.*');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
