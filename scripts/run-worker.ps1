# Drains the database queue then exits. Invoked every 5 minutes by the
# PgsQueueWorker Windows task (registered via register-scheduler-tasks.ps1).
$ErrorActionPreference = 'Continue'

$app = 'C:\xampp\htdocs\pgs\app'
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }

# Keep the log from growing without bound (Laravel rotation does not cover it).
$log = Join-Path $app 'storage\logs\worker.log'
if ((Test-Path $log) -and ((Get-Item $log).Length -gt 10MB)) {
    Move-Item -Force -LiteralPath $log -Destination "$log.1" -ErrorAction SilentlyContinue
    Set-Content -LiteralPath $log -Value '' -ErrorAction SilentlyContinue
}

Set-Location $app
# --timeout must stay below DB_QUEUE_RETRY_AFTER (720) so a job is never
# re-picked-up while still running. RunBackupJob allows up to 600s.
& $php artisan queue:work --stop-when-empty --sleep=3 --tries=3 --timeout=600 --backoff=5 --max-time=600 *>> $log
