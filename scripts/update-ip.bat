@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  PGS Update IP Script
REM  Auto-detects LAN IP and updates .env APP_URL.
REM  Usage: scripts\update-ip.bat
REM ─────────────────────────────────────────────────────────────────────────────

setlocal EnableDelayedExpansion

echo ═══════════════════════════════════════════════════════════════
echo  PGS Update IP
echo ═══════════════════════════════════════════════════════════════
echo.

REM ── Step 1: Detect LAN IP ──────────────────────────────────────────────────
echo [1/4] Detecting LAN IP...

set LAN_IP=
for /f "tokens=2 delims=:" %%A in ('ipconfig ^| findstr /i "IPv4"') do (
    set "RAW=%%A"
    set "RAW=!RAW: =!"
    if "!RAW!" neq "127.0.0.1" (
        if "!LAN_IP!"=="" set LAN_IP=!RAW!
    )
)

if "!LAN_IP!"=="" (
    echo [FAIL] Could not detect LAN IP
    exit /b 1
)

echo   Found: !LAN_IP!
echo.

REM ── Step 2: Update .env ───────────────────────────────────────────────────
echo [2/4] Updating .env...

set ENV_FILE=%~dp0..\app\.env
if not exist "%ENV_FILE%" (
    echo [FAIL] .env not found at %ENV_FILE%
    exit /b 1
)

REM Replace APP_URL line
powershell -Command "(Get-Content '%ENV_FILE%') -replace 'APP_URL=.*', 'APP_URL=http://!LAN_IP!:8082' | Set-Content '%ENV_FILE%'"
echo   Updated APP_URL to http://!LAN_IP!:8082
echo.

REM ── Step 3: Clear caches ──────────────────────────────────────────────────
echo [3/4] Clearing caches...
cd /d "%~dp0..\app"
php artisan config:cache
php artisan route:cache
echo   Caches cleared
echo.

REM ── Step 4: Summary ───────────────────────────────────────────────────────
echo [4/4] Done!
echo.
echo ═══════════════════════════════════════════════════════════════
echo  PGS is now accessible at:
echo  http://!LAN_IP!:8082
echo ═══════════════════════════════════════════════════════════════

exit /b 0
