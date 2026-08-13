<?php

declare(strict_types=1);

use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\RoadmapController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware('auth')->group(function (): void {
        Route::get('/deliverables', [DeliverableController::class, 'index'])->name('deliverables.index');
        Route::get('/deliverables/create', [DeliverableController::class, 'create'])->name('deliverables.create');
        Route::post('/deliverables', [DeliverableController::class, 'store'])
            ->middleware('throttle:submissions')->name('deliverables.store');
        Route::get('/deliverables/{deliverable}/edit', [DeliverableController::class, 'edit'])->name('deliverables.edit');
        Route::put('/deliverables/{deliverable}', [DeliverableController::class, 'update'])->name('deliverables.update');
        Route::delete('/deliverables/{deliverable}', [DeliverableController::class, 'destroy'])->name('deliverables.destroy');
        Route::get('/deliverables/{deliverable}/download', [DeliverableController::class, 'download'])->name('deliverables.download');
        Route::post('/deliverables/{deliverable}/status', [DeliverableController::class, 'transition'])->name('deliverables.transition');

        Route::get('/roadmaps', [RoadmapController::class, 'index'])
            ->middleware('page.access:roadmaps')->name('roadmaps.index');
        Route::post('/roadmaps/titles', [RoadmapController::class, 'storeTitle'])
            ->middleware('page.access:roadmaps')->name('roadmaps.titles.store');
        Route::put('/roadmaps/titles/{title}', [RoadmapController::class, 'updateTitle'])
            ->middleware('page.access:roadmaps')->name('roadmaps.titles.update');
        Route::delete('/roadmaps/titles/{title}', [RoadmapController::class, 'destroyTitle'])
            ->middleware('page.access:roadmaps')->name('roadmaps.titles.destroy');
        Route::post('/roadmaps/titles/{title}/items', [RoadmapController::class, 'storeItem'])
            ->middleware('page.access:roadmaps')->name('roadmaps.items.store');
        Route::put('/roadmaps/items/{item}', [RoadmapController::class, 'updateItem'])
            ->middleware('page.access:roadmaps')->name('roadmaps.items.update');
        Route::delete('/roadmaps/items/{item}', [RoadmapController::class, 'destroyItem'])
            ->middleware('page.access:roadmaps')->name('roadmaps.items.destroy');
        Route::post('/roadmaps/items/{item}/reorder', [RoadmapController::class, 'reorderItem'])
            ->middleware('page.access:roadmaps')->name('roadmaps.items.reorder');
        Route::post('/roadmaps/items/{item}/blocks', [RoadmapController::class, 'storeBlock'])
            ->middleware('page.access:roadmaps')->name('roadmaps.blocks.store');
        Route::put('/roadmaps/blocks/{block}', [RoadmapController::class, 'updateBlock'])
            ->middleware('page.access:roadmaps')->name('roadmaps.blocks.update');
        Route::delete('/roadmaps/blocks/{block}', [RoadmapController::class, 'destroyBlock'])
            ->middleware('page.access:roadmaps')->name('roadmaps.blocks.destroy');

        Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
        Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');
        Route::put('/notices/{notice}', [NoticeController::class, 'update'])->name('notices.update');
        Route::delete('/notices/{notice}', [NoticeController::class, 'destroy'])->name('notices.destroy');
    });
});
