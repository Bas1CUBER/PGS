<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// ── Queue Maintenance ────────────────────────────────────────────────────────

// Prune dead (failed) jobs older than 48 hours daily.
Schedule::command('queue:prune-dead', ['--hours' => 48])->dailyAt('03:45');

// Restart queue workers daily to prevent memory leaks.
Schedule::command('queue:restart')->dailyAt('03:30');

// ── Cache Maintenance ────────────────────────────────────────────────────────

// Warm critical cache entries before business hours.
Schedule::command('cache:warm')->dailyAt('07:50');

// ── Deadline Management ──────────────────────────────────────────────────────

// Auto-disable expired deadlines every 5 minutes.
Schedule::command('deadline:check-expiry')->everyFiveMinutes();

// ── Cleanup ──────────────────────────────────────────────────────────────────

// Prune old outbox mails (sent or pending > 7 days).
Schedule::command('outbox:prune', ['--days' => 7])->dailyAt('03:15');

// ── Scheduler Heartbeat ─────────────────────────────────────────────────────

// Written once per minute so a silently-stalled scheduler is visible on
// health pages instead of only surfacing as stale backups hours later.
Schedule::call(function (): void {
    Cache::put('scheduler:heartbeat', now()->toIso8601String(), 300);
})->name('scheduler:heartbeat')->everyMinute()->withoutOverlapping();
