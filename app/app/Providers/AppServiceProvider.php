<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // PGS password policy: min 12 chars; breached-password check against
        // HaveIBeenPwned only in production (requires network).
        Password::defaults(function (): Password {
            $rule = Password::min(12);

            if (app(Application::class)->isProduction()) {
                $rule->uncompromised();
            }

            return $rule;
        });

        // Login throttling: 5 attempts per minute per IP+email, then lockout.
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->input('email').'|'.$request->ip());
        });
    }
}
