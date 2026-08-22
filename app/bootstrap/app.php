<?php

use App\Http\Middleware\CanAccessPageMiddleware;
use App\Http\Middleware\EnsureActiveUserMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LegacyRedirectMiddleware;
use App\Http\Middleware\OptionalCanAccessPageMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Route files loaded here must opt into the web middleware group
            // (session, CSRF, SubstituteBindings) explicitly.
            Route::middleware('web')->group(function (): void {
                require __DIR__.'/../routes/notifications.php';
                require __DIR__.'/../routes/users.php';
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/modules.php';
                require __DIR__.'/../routes/sectors.php';
                require __DIR__.'/../routes/content_modules.php';
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            LegacyRedirectMiddleware::class,
            EnsureActiveUserMiddleware::class,
            HandleInertiaRequests::class,
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'page.access' => CanAccessPageMiddleware::class,
            'page.access.optional' => OptionalCanAccessPageMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
