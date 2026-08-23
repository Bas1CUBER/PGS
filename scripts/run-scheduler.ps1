# Runs all due Laravel scheduled tasks (nightly backups, deadline expiry,
# outbox prune, cache warm, heartbeats). Invoked every minute by the
# PgsScheduler Windows task (registered via register-scheduler-tasks.ps1).
$ErrorActionPreference = 'Continue'

$app = 'C:\xampp\htdocs\pgs\app'
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }

Set-Location $app
& $php artisan schedule:run *>> storage\logs\scheduler.log
