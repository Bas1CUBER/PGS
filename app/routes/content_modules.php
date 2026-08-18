<?php

declare(strict_types=1);

use App\Http\Controllers\CommPlanController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ImpactScorecardController;
use App\Http\Controllers\LegacyFormController;
use App\Http\Controllers\OperationsReviewController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\SectorDetailController;
use App\Http\Controllers\StaticContentController;
use App\Http\Controllers\StrategyReviewController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\UploadModuleController;
use App\Modules\UploadModuleRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified'])->group(function (): void {
        // Upload modules (config-driven)
        Route::get('/uploads', [UploadModuleController::class, 'index'])->name('uploads.index');
        Route::get('/uploads/{slug}', [UploadModuleController::class, 'legacyShow'])->name('uploads.show');
        Route::post('/uploads/{slug}', [UploadModuleController::class, 'store'])
            ->middleware('throttle:submissions')->name('uploads.store');
        Route::get('/uploads/{slug}/{id}/download', [UploadModuleController::class, 'download'])->name('uploads.download');
        Route::delete('/uploads/{slug}/{id}', [UploadModuleController::class, 'destroy'])
            ->name('uploads.destroy');
        Route::put('/uploads/{slug}/{id}/status', [UploadModuleController::class, 'updateStatus'])
            ->middleware('role:admin,focal')->name('uploads.status');
        Route::post('/uploads/{slug}/templates', [UploadModuleController::class, 'templateStore'])
            ->middleware('role:admin')->name('uploads.templates.store');
        Route::get('/uploads/{slug}/templates/{template}/download', [UploadModuleController::class, 'templateDownload'])
            ->name('uploads.templates.download');
        Route::delete('/uploads/{slug}/templates/{template}', [UploadModuleController::class, 'templateDestroy'])
            ->middleware('role:admin')->name('uploads.templates.destroy');

        // Canonical module-owned upload workspaces. The legacy /uploads/{slug}
        // endpoints remain available for old links and POST clients, while all
        // new navigation and generated actions use /{slug}/upload.
        foreach (UploadModuleRegistry::slugs() as $slug) {
            Route::prefix('/'.$slug.'/upload')->group(function () use ($slug): void {
                Route::get('/', [UploadModuleController::class, 'show'])
                    ->defaults('slug', $slug)->name($slug.'.upload.index');
                Route::post('/', [UploadModuleController::class, 'store'])
                    ->defaults('slug', $slug)->middleware('throttle:submissions')->name($slug.'.upload.store');
                Route::get('/{id}/download', [UploadModuleController::class, 'download'])
                    ->defaults('slug', $slug)->whereNumber('id')->name($slug.'.upload.download');
                Route::get('/{id}/pdf', [PdfExportController::class, 'uploadRecord'])
                    ->defaults('slug', $slug)->whereNumber('id')->name($slug.'.upload.pdf');
                Route::delete('/{id}', [UploadModuleController::class, 'destroy'])
                    ->defaults('slug', $slug)->whereNumber('id')->name($slug.'.upload.destroy');
                Route::put('/{id}/status', [UploadModuleController::class, 'updateStatus'])
                    ->defaults('slug', $slug)->whereNumber('id')->middleware('role:admin,focal')->name($slug.'.upload.status');
                Route::post('/templates', [UploadModuleController::class, 'templateStore'])
                    ->defaults('slug', $slug)->middleware('role:admin')->name($slug.'.upload.templates.store');
                Route::get('/templates/{template}/download', [UploadModuleController::class, 'templateDownload'])
                    ->defaults('slug', $slug)->whereNumber('template')->name($slug.'.upload.templates.download');
                Route::delete('/templates/{template}', [UploadModuleController::class, 'templateDestroy'])
                    ->defaults('slug', $slug)->whereNumber('template')->middleware('role:admin')->name($slug.'.upload.templates.destroy');
            });
        }

        // Communication plan template
        Route::middleware('page.access:cascading')->group(function (): void {
            Route::get('/communication-plan', [CommPlanController::class, 'index'])->name('comm-plan.index');
            Route::post('/communication-plan', [CommPlanController::class, 'store'])->name('comm-plan.store');
            Route::put('/communication-plan/{row}', [CommPlanController::class, 'update'])->name('comm-plan.update');
            Route::delete('/communication-plan/{row}', [CommPlanController::class, 'destroy'])->name('comm-plan.destroy');
        });

        // Structured Strategy Review form (legacy strategy_review_form.php)
        Route::middleware('page.access:performance_assessment')->group(function (): void {
            Route::get('/strategy-review', [StrategyReviewController::class, 'index'])->name('strategy-review.index');
            Route::post('/strategy-review', [StrategyReviewController::class, 'store'])->name('strategy-review.store');
            Route::put('/strategy-review/{form}', [StrategyReviewController::class, 'update'])->name('strategy-review.update');
            Route::post('/strategy-review/{form}/review', [StrategyReviewController::class, 'review'])->name('strategy-review.review');
        });

        // Structured Operations Review form plus the existing upload register.
        Route::middleware('page.access:performance_assessment')->group(function (): void {
            Route::get('/operations-review', [OperationsReviewController::class, 'index'])->name('operations-review.index');
            Route::post('/operations-review', [OperationsReviewController::class, 'store'])->name('operations-review.store');
            Route::put('/operations-review/{review}', [OperationsReviewController::class, 'update'])->name('operations-review.update');
            Route::delete('/operations-review/{review}', [OperationsReviewController::class, 'destroy'])->name('operations-review.destroy');
            Route::get('/operations-review/{review}/pdf', [PdfExportController::class, 'operationsReview'])->name('operations-review.pdf');
            Route::get('/strategy-review/{form}/pdf', [PdfExportController::class, 'strategyReview'])->name('strategy-review.pdf');
        });

        // Legacy Annex workspaces and the admin-only OPCR target register.
        Route::middleware('page.access:performance_assessment')->group(function (): void {
            Route::get('/annex/{slug}', [LegacyFormController::class, 'annex'])
                ->where('slug', 'annex-[bdehjk]')->name('annex.show');
            Route::post('/annex/{slug}', [LegacyFormController::class, 'annexStore'])
                ->where('slug', 'annex-[bdehjk]')->name('annex.store');
            Route::put('/annex/{slug}/{id}', [LegacyFormController::class, 'annexUpdate'])
                ->where('slug', 'annex-[bdehjk]')->name('annex.update');
            Route::delete('/annex/{slug}/{id}', [LegacyFormController::class, 'annexDestroy'])
                ->where('slug', 'annex-[bdehjk]')->name('annex.destroy');
            Route::get('/annex/{slug}/download', [LegacyFormController::class, 'annexDownload'])
                ->where('slug', 'annex-[bdehjk]')->name('annex.download');
        });
        Route::middleware(['page.access:performance_assessment', 'role:admin'])->group(function (): void {
            Route::get('/opcr', [LegacyFormController::class, 'opcr'])->name('opcr.index');
            Route::get('/opcr/export', [LegacyFormController::class, 'opcrDownload'])->name('opcr.export');
            Route::post('/opcr', [LegacyFormController::class, 'opcrStore'])->name('opcr.store');
            Route::put('/opcr/{id}', [LegacyFormController::class, 'opcrUpdate'])->name('opcr.update');
            Route::delete('/opcr/{id}', [LegacyFormController::class, 'opcrDestroy'])->name('opcr.destroy');
        });

        // Gallery
        Route::middleware('page.access:cascading')->group(function (): void {
            Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
            Route::middleware('role:admin,focal')->group(function (): void {
                Route::post('/gallery/albums', [GalleryController::class, 'storeAlbum'])->name('gallery.albums.store');
                Route::put('/gallery/albums/{album}', [GalleryController::class, 'updateAlbum'])->name('gallery.albums.update');
                Route::delete('/gallery/albums/{album}', [GalleryController::class, 'destroyAlbum'])->name('gallery.albums.destroy');
                Route::post('/gallery/albums/{album}/photos', [GalleryController::class, 'storePhoto'])->name('gallery.photos.store');
                Route::put('/gallery/photos/{photo}', [GalleryController::class, 'updatePhoto'])->name('gallery.photos.update');
                Route::delete('/gallery/photos/{photo}', [GalleryController::class, 'destroyPhoto'])->name('gallery.photos.destroy');
            });
            Route::get('/gallery/photos/{photo}/file', [GalleryController::class, 'photoFile'])->name('gallery.photos.file');
        });

        // Impact scorecard
        Route::middleware('page.access:scorecard')->group(function (): void {
            Route::get('/impact-scorecard', [ImpactScorecardController::class, 'index'])->name('scorecard.index');
            Route::middleware('role:admin,focal')->group(function (): void {
                Route::post('/impact-scorecard/measures', [ImpactScorecardController::class, 'storeMeasure'])->name('scorecard.measures.store');
                Route::put('/impact-scorecard/measures/{measure}', [ImpactScorecardController::class, 'updateMeasure'])->name('scorecard.measures.update');
                Route::delete('/impact-scorecard/measures/{measure}', [ImpactScorecardController::class, 'destroyMeasure'])->name('scorecard.measures.destroy');
                Route::post('/impact-scorecard/years', [ImpactScorecardController::class, 'storeYear'])->name('scorecard.years.store');
                Route::delete('/impact-scorecard/years/{year}', [ImpactScorecardController::class, 'destroyYear'])->name('scorecard.years.destroy');
                Route::put('/impact-scorecard/values/{measure}/{year}', [ImpactScorecardController::class, 'updateValue'])->name('scorecard.values.update');
            });
        });

        // Survey
        Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
        Route::post('/surveys/{survey}/done', [SurveyController::class, 'markDone'])->name('surveys.done');
        Route::middleware('role:admin')->group(function (): void {
            Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
            Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
            Route::post('/surveys/{survey}/archive', [SurveyController::class, 'archive'])->name('surveys.archive');
            Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');
        });

        // Sector detail roadmaps (config-driven wide tables), nested under
        // their pillar: /sectors/{pillar}/{slug} (e.g. /sectors/collab/relapse-rate).
        Route::middleware('page.access:roadmaps')->group(function (): void {
            Route::get('/sectors/{pillar}/{slug}', [SectorDetailController::class, 'show'])->name('sectors.details.show');
            Route::get('/sectors/{pillar}/{slug}/export', [SectorDetailController::class, 'export'])->name('sectors.details.export');
            Route::post('/sectors/{pillar}/{slug}', [SectorDetailController::class, 'store'])->middleware('role:admin,focal')->name('sectors.details.store');
            Route::put('/sectors/{pillar}/{slug}/{id}', [SectorDetailController::class, 'update'])->middleware('role:admin,focal')->name('sectors.details.update');
            Route::delete('/sectors/{pillar}/{slug}/{id}', [SectorDetailController::class, 'destroy'])->middleware('role:admin,focal')->name('sectors.details.destroy');
            Route::post('/sectors/{pillar}/{slug}/{id}/lock', [SectorDetailController::class, 'toggleLock'])->middleware('role:admin,focal')->name('sectors.details.lock');
        });

        // Static content pages
        Route::get('/content/{slug}', [StaticContentController::class, 'show'])->name('content.show');
        Route::post('/content/{slug}/image', [StaticContentController::class, 'replaceImage'])->name('content.image');
        Route::post('/content/{slug}/structured', [StaticContentController::class, 'saveStructured'])->name('content.structured');

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
        })->where('name', '[A-Za-z0-9_.%-]+')->name('legacy-img');
    });
});
