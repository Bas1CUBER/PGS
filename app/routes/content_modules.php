<?php

declare(strict_types=1);

use App\Http\Controllers\CommPlanController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ImpactScorecardController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\SectorDetailController;
use App\Http\Controllers\StaticContentController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\UploadModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware('auth')->group(function (): void {
        // Upload modules (config-driven)
        Route::get('/uploads', [UploadModuleController::class, 'index'])->name('uploads.index');
        Route::get('/uploads/{slug}', [UploadModuleController::class, 'show'])->name('uploads.show');
        Route::post('/uploads/{slug}', [UploadModuleController::class, 'store'])->name('uploads.store');
        Route::get('/uploads/{slug}/{id}/download', [UploadModuleController::class, 'download'])->name('uploads.download');
        Route::delete('/uploads/{slug}/{id}', [UploadModuleController::class, 'destroy'])->name('uploads.destroy');
        Route::put('/uploads/{slug}/{id}/status', [UploadModuleController::class, 'updateStatus'])->name('uploads.status');

        // Communication plan template
        Route::get('/communication-plan', [CommPlanController::class, 'index'])->name('comm-plan.index');
        Route::post('/communication-plan', [CommPlanController::class, 'store'])->name('comm-plan.store');
        Route::put('/communication-plan/{row}', [CommPlanController::class, 'update'])->name('comm-plan.update');
        Route::delete('/communication-plan/{row}', [CommPlanController::class, 'destroy'])->name('comm-plan.destroy');

        // Gallery
        Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
        Route::post('/gallery/albums', [GalleryController::class, 'storeAlbum'])->name('gallery.albums.store');
        Route::delete('/gallery/albums/{album}', [GalleryController::class, 'destroyAlbum'])->name('gallery.albums.destroy');
        Route::post('/gallery/albums/{album}/photos', [GalleryController::class, 'storePhoto'])->name('gallery.photos.store');
        Route::delete('/gallery/photos/{photo}', [GalleryController::class, 'destroyPhoto'])->name('gallery.photos.destroy');
        Route::get('/gallery/photos/{photo}/file', [GalleryController::class, 'photoFile'])->name('gallery.photos.file');

        // Impact scorecard
        Route::get('/impact-scorecard', [ImpactScorecardController::class, 'index'])->name('scorecard.index');
        Route::post('/impact-scorecard/measures', [ImpactScorecardController::class, 'storeMeasure'])->name('scorecard.measures.store');
        Route::put('/impact-scorecard/measures/{measure}', [ImpactScorecardController::class, 'updateMeasure'])->name('scorecard.measures.update');
        Route::delete('/impact-scorecard/measures/{measure}', [ImpactScorecardController::class, 'destroyMeasure'])->name('scorecard.measures.destroy');
        Route::post('/impact-scorecard/years', [ImpactScorecardController::class, 'storeYear'])->name('scorecard.years.store');
        Route::delete('/impact-scorecard/years/{year}', [ImpactScorecardController::class, 'destroyYear'])->name('scorecard.years.destroy');
        Route::put('/impact-scorecard/values/{measure}/{year}', [ImpactScorecardController::class, 'updateValue'])->name('scorecard.values.update');

        // Survey
        Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
        Route::post('/surveys/{survey}/done', [SurveyController::class, 'markDone'])->name('surveys.done');

        // Sector detail roadmaps (config-driven wide tables)
        Route::get('/sector-details/{slug}', [SectorDetailController::class, 'show'])->name('sector-details.show');
        Route::put('/sector-details/{slug}/{id}', [SectorDetailController::class, 'update'])->name('sector-details.update');

        // Static content pages
        Route::get('/content/{slug}', [StaticContentController::class, 'show'])->name('content.show');
        Route::post('/content/{slug}/image', [StaticContentController::class, 'replaceImage'])->name('content.image');

        // PDF exports
        Route::get('/uploads/{slug}/{id}/pdf', [PdfExportController::class, 'uploadRecord'])->name('uploads.pdf');
        Route::get('/deliverables/{id}/pdf', [PdfExportController::class, 'deliverable'])->name('deliverables.pdf');

        // Serve images from the shared img/ directory (outside the app public dir).
        Route::get('/legacy-img/{name}', function (string $name) {
            $name = basename(urldecode($name));
            $path = base_path('../img/'.$name);

            if (! is_file($path)) {
                abort(404);
            }

            return response()->file($path);
        })->where('name', '.*')->name('legacy-img');
    });
});
