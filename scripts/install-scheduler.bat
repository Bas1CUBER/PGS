@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  PGS Scheduler Installer
REM  Registers the Windows scheduled tasks that keep background work alive:
REM    PGS Scheduler     every minute  -> scripts\run-scheduler.bat
REM    PGS Queue Worker  every 5 min   -> scripts\run-worker.bat
REM  Idempotent: re-running recreates both tasks.
REM  Usage: scripts\install-scheduler.bat   (run as Administrator)
REM ─────────────────────────────────────────────────────────────────────────────

setlocal EnableDelayedExpansion

set SCRIPTS_DIR=%~dp0
if "%SCRIPTS_DIR:~-1%"=="\" set SCRIPTS_DIR=%SCRIPTS_DIR:~0,-1%

echo ═══════════════════════════════════════════════════════════════
echo  PGS Scheduled Tasks Installer
echo ═══════════════════════════════════════════════════════════════
echo.

net session >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Please run as Administrator ^(tasks are registered for SYSTEM^)
    exit /b 1
)

schtasks /create /f /tn "PGS Scheduler" /sc MINUTE /mo 1 /ru SYSTEM /rl HIGHEST /tr "\"%SCRIPTS_DIR%\run-scheduler.bat\""
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Could not register "PGS Scheduler"
    exit /b 1
)
echo   ✓ Registered "PGS Scheduler" (every minute)

schtasks /create /f /tn "PGS Queue Worker" /sc MINUTE /mo 5 /ru SYSTEM /rl HIGHEST /tr "\"%SCRIPTS_DIR%\run-worker.bat\""
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Could not register "PGS Queue Worker"
    echo [ROLLBACK] Removing scheduler task...
    schtasks /delete /tn "PGS Scheduler" /f >NUL 2>&1
    exit /b 1
)
echo   ✓ Registered "PGS Queue Worker" (every 5 minutes)

echo.
echo Kicking off an initial run...
schtasks /run /tn "PGS Scheduler" >NUL
echo.

schtasks /query /tn "PGS Scheduler"
schtasks /query /tn "PGS Queue Worker"
echo.
echo [DONE] Background processing is now active.
echo        Logs: app\storage\logs\scheduler.log and worker.log
echo        To remove: schtasks /delete /tn "PGS Scheduler" /f ^&^& schtasks /delete /tn "PGS Queue Worker" /f

exit /b 0
