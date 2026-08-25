<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\DeliverableController;
use App\Mail\OutboxTransport;
use App\Services\TransitionsWorkflowService;
use App\Services\WorkflowRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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
        if ($this->app->isProduction() && blank(config('backup.backup.password'))) {
            throw new \RuntimeException('BACKUP_ARCHIVE_PASSWORD must be configured in production.');
        }

        // #101: backup notifications fall back to hello@example.com — require explicit
        // BACKUP_NOTIFY_EMAIL in production so failures are not silently misdirected.
        if ($this->app->isProduction() && (blank(config('backup.notifications.mail.to')) || config('backup.notifications.mail.to') === 'hello@example.com')) {
            throw new \RuntimeException('BACKUP_NOTIFY_EMAIL must be configured in production.');
        }

        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureWorkflow();
        $this->registerQueueHealth();
    }

    protected function configureDefaults(): void
    {
        // PGS password policy: min 12 chars; breached-password check against
        // HaveIBeenPwned only in production (requires network).
        Password::defaults(function (): Password {
            $rule = Password::min(12);

            if (app(Application::class)->isProduction()) {
                $rule->uncompromised();
            }

            return $rule;
        });

        // Catch lazy-loaded relationships in production to prevent N+1 queries.
        // In non-production environments this throws, surfacing N+1s in tests.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Log slow queries (>200ms) in all non-testing environments.
        if (app()->environment('local', 'production')) {
            DB::listen(function ($query): void {
                if ($query->time > 200) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => round($query->time, 2),
                    ]);
                }
            });
        }

        // Local fallback mailer: messages land in outbox_mails when selected.
        Mail::extend('outbox', fn (): OutboxTransport => new OutboxTransport);
    }

    protected function configureRateLimiting(): void
    {
        // Login throttling: 5 attempts per minute per IP+email, then lockout.
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('password-reset-verify', function (Request $request): Limit {
            return Limit::perMinute(10)->by('ip:'.$request->ip().'|session:'.$request->session()->getId());
        });

        RateLimiter::for('password-reset-resend', function (Request $request): Limit {
            return Limit::perMinute(3)->by('ip:'.$request->ip().'|session:'.$request->session()->getId());
        });

        RateLimiter::for('password-reset-change', function (Request $request): Limit {
            return Limit::perMinute(5)->by('ip:'.$request->ip().'|session:'.$request->session()->getId());
        });

        // Upload/create throttling: 30 submissions per minute per user.
        RateLimiter::for('submissions', function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // Export throttling: 5 exports per minute per user (heavy operations).
        RateLimiter::for('exports', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }

    protected function configureWorkflow(): void
    {
        // Maps live in WorkflowRegistry so tests exercise the same
        // production definitions instead of ad-hoc copies.
        $this->app->when(DeliverableController::class)
            ->needs(TransitionsWorkflowService::class)
            ->give(fn (): TransitionsWorkflowService => WorkflowRegistry::deliverables());
    }

    protected function registerQueueHealth(): void
    {
        // Track when the worker last processed a job (for health checks).
        Queue::after(function (): void {
            Cache::put('queue:worker:last_processed', now()->toIso8601String(), now()->addMinutes(2));
        });

        // Keep the worker heartbeat fresh even while the queue is idle.
        // The after-job heartbeat above expires within 2 minutes, so a healthy
        // worker with nothing to process would otherwise report "unknown".
        Queue::looping(function (): void {
            if (! Cache::add('queue:worker:heartbeat_write_lock', true, 30)) {
                return;
            }

            Cache::put('queue:worker:last_processed', now()->toIso8601String(), now()->addMinutes(2));
        });

        // Log failed jobs for observability.
        Queue::failing(function (JobFailed $event): void {
            Log::error('Queue job failed', [
                'job' => $event->job->getName(),
                'payload' => $event->job->payload(),
                'error' => $event->exception->getMessage(),
            ]);
        });
    }
}
