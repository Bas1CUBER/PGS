<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('/users')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('users.index');
    Route::get('/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/', [UserController::class, 'store'])->name('users.store');
    Route::post('/import', [UserController::class, 'import'])->name('users.import');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('/{user}/access', [UserController::class, 'updateAccess'])->name('users.update-access');
    Route::post('/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
