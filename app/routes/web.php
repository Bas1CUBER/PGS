<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/up', function () {
    try {
        DB::select('SELECT 1');
        $services = ['database' => 'up'];
        $status = 200;
    } catch (Throwable) {
        $services = ['database' => 'down'];
        $status = 503;
    }

    return response()->json([
        'status' => $status === 200 ? 'up' : 'degraded',
        'services' => $services,
    ], $status);
})->name('health');
