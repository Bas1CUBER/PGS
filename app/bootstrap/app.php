<?php

use App\Http\Middleware\CanAccessPageMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // Route files loaded here must opt into the web middleware group
            // (session, CSRF, SubstituteBindings) explicitly.
            Route::middleware('web')->group(function (): void {
                require __DIR__.'/../routes/notifications.php';
                require __DIR__.'/../routes/users.php';
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/modules.php';
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'page.access' => CanAccessPageMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
