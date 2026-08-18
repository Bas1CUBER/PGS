@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  PGS Health Check Script
REM  Verifies the application is running and healthy after deployment.
REM  Usage: scripts\health-check.bat
REM ─────────────────────────────────────────────────────────────────────────────

setlocal EnableDelayedExpansion

set APP_URL=http://127.0.0.1:8082
set HEALTH_ENDPOINT=%APP_URL%/up
set MAX_RETRIES=5
set RETRY_DELAY=2

echo [HEALTH] Checking PGS application health...
echo [HEALTH] Target: %HEALTH_ENDPOINT%

set attempt=1
:retry
echo [HEALTH] Attempt %attempt%/%MAX_RETRIES%...

REM Use curl to check the health endpoint
curl -s -o NUL -w "%%{http_code}" "%HEALTH_ENDPOINT%" > "%TEMP%\pgs_health_code.txt" 2>NUL
set /p HTTP_CODE=<"%TEMP%\pgs_health_code.txt"

if "%HTTP_CODE%"=="200" (
    echo [HEALTH] OK - Application is healthy (HTTP %HTTP_CODE%)
    del "%TEMP%\pgs_health_code.txt" 2>NUL
    exit /b 0
)

if %attempt% geq %MAX_RETRIES% (
    echo [HEALTH] FAILED - Application did not respond after %MAX_RETRIES% attempts
    echo [HEALTH] Last HTTP code: %HTTP_CODE%
    del "%TEMP%\pgs_health_code.txt" 2>NUL
    exit /b 1
)

echo [HEALTH] Not ready (HTTP %HTTP_CODE%), retrying in %RETRY_DELAY%s...
timeout /t %RETRY_DELAY% /nobreak >NUL
set /a attempt+=1
goto retry
