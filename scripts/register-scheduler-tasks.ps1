# Run this ONCE (no admin needed) to register the PGS background tasks.
# Tasks run as the currently logged-in interactive user with hidden windows:
#   PgsScheduler     every 1 min -> scripts\run-scheduler.ps1
#   PgsQueueWorker   every 5 min -> scripts\run-worker.ps1
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File "C:\xampp\htdocs\pgs\scripts\register-scheduler-tasks.ps1"

param(
    [string]$UserName = ""   # leave blank to auto-detect the current interactive user
)

$schedulerScript = 'C:\xampp\htdocs\pgs\scripts\run-scheduler.ps1'
$workerScript    = 'C:\xampp\htdocs\pgs\scripts\run-worker.ps1'

# Auto-detect the interactive user if not supplied.
if (-not $UserName) {
    $qwinsta = "$env:SystemRoot\System32\qwinsta.exe"
    if (Test-Path $qwinsta) {
        $sessionUser = (& $qwinsta 2>$null |
            Where-Object { $_ -match '\bActive\b' } |
            ForEach-Object { ($_ -split '\s+') | Where-Object { $_ -ne '' } | Select-Object -Index 1 } |
            Select-Object -First 1)
    }

    if ($sessionUser) {
        $UserName = "$env:USERDOMAIN\$sessionUser"
    } else {
        $UserName = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
    }
}

Write-Output "Registering tasks for user: $UserName"

# Replace legacy bat-based tasks from earlier installs, if present.
foreach ($legacy in @('PGS Scheduler', 'PGS Queue Worker')) {
    Unregister-ScheduledTask -TaskName $legacy -Confirm:$false -ErrorAction SilentlyContinue
}

function Register-PgsTask([string]$Name, [string]$Script, [int]$Minutes, [int]$LimitMinutes) {
    Unregister-ScheduledTask -TaskName $Name -Confirm:$false -ErrorAction SilentlyContinue

    # wscript is a GUI-subsystem host: launching through run-ps-hidden.vbs
    # never allocates a console, unlike powershell.exe which flashes one
    # briefly before -WindowStyle Hidden applies.
    $launcher = 'C:\xampp\htdocs\pgs\scripts\run-ps-hidden.vbs'
    $action = New-ScheduledTaskAction `
        -Execute 'wscript.exe' `
        -Argument "`"$launcher`" `"$Script`""

    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
        -RepetitionInterval (New-TimeSpan -Minutes $Minutes) `
        -RepetitionDuration (New-TimeSpan -Days 3650)

    $settings = New-ScheduledTaskSettingsSet `
        -ExecutionTimeLimit (New-TimeSpan -Minutes $LimitMinutes) `
        -MultipleInstances Queue `
        -Hidden `
        -StartWhenAvailable

    $principal = New-ScheduledTaskPrincipal `
        -UserId $UserName `
        -LogonType Interactive `
        -RunLevel Limited

    Register-ScheduledTask `
        -TaskName $Name `
        -Action $action `
        -Trigger $trigger `
        -Settings $settings `
        -Principal $principal `
        -Force | Out-Null

    Write-Output "Task '$Name' registered -> $Script (every $Minutes min)"
}

Register-PgsTask -Name 'PgsScheduler'   -Script $schedulerScript -Minutes 1 -LimitMinutes 5
Register-PgsTask -Name 'PgsQueueWorker' -Script $workerScript    -Minutes 5 -LimitMinutes 15

Write-Output ""
Write-Output "Done. Background processing is active while this user is logged in."
