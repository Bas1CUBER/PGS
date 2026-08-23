# Drains the database queue then exits. Invoked every 5 minutes by the
# PgsQueueWorker Windows task (registered via register-scheduler-tasks.ps1).
$ErrorActionPreference = 'Continue'

$app = 'C:\xampp\htdocs\pgs\app'
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }

Set-Location $app
& $php artisan queue:work --stop-when-empty --sleep=3 --tries=3 --max-time=600 *>> storage\logs\worker.log
