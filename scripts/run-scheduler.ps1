# Runs all due Laravel scheduled tasks (nightly backups, deadline expiry,
# outbox prune, cache warm, heartbeats). Invoked every minute by the
# PgsScheduler Windows task (registered via register-scheduler-tasks.ps1).
$ErrorActionPreference = 'Continue'

$app = 'C:\xampp\htdocs\pgs\app'
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }

# Keep the log from growing without bound (Laravel rotation does not cover it).
$log = Join-Path $app 'storage\logs\scheduler.log'
if ((Test-Path $log) -and ((Get-Item $log).Length -gt 10MB)) {
    Move-Item -Force -LiteralPath $log -Destination "$log.1" -ErrorAction SilentlyContinue
    Set-Content -LiteralPath $log -Value '' -ErrorAction SilentlyContinue
}

Set-Location $app
& $php artisan schedule:run *>> $log
