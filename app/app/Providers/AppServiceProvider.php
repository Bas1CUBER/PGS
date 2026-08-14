<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\DeliverableStatus;
use App\Http\Controllers\DeliverableController;
use App\Mail\OutboxTransport;
use App\Models\Deliverable;
use App\Services\TransitionsWorkflowService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        // LAN mail: no SMTP server on the host — messages land in outbox_mails.
        Mail::extend('outbox', fn (): OutboxTransport => new OutboxTransport);

        // Upload/create throttling: 30 submissions per minute per user.
        RateLimiter::for('submissions', function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // Deliverable progress workflow (docs/Workflows.md §2).
        $this->app->when(DeliverableController::class)
            ->needs(TransitionsWorkflowService::class)
            ->give(fn (): TransitionsWorkflowService => new TransitionsWorkflowService(
                Deliverable::class,
                [
                    DeliverableStatus::NotYetStarted->value => [
                        ['to' => DeliverableStatus::Ongoing->value, 'actor' => '*'],
                        ['to' => DeliverableStatus::Accomplished->value, 'actor' => 'admin|focal'],
                    ],
                    DeliverableStatus::Ongoing->value => [
                        ['to' => DeliverableStatus::Accomplished->value, 'actor' => '*'],
                        ['to' => DeliverableStatus::NotYetStarted->value, 'actor' => 'admin|focal'],
                    ],
                    DeliverableStatus::Accomplished->value => [
                        ['to' => DeliverableStatus::Ongoing->value, 'actor' => 'admin|focal'],
                    ],
                ],
            ));
    }
}
