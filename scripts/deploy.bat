@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  PGS Deploy Script
REM  Safe deployment with pre-flight checks, backup, and rollback.
REM  Usage: scripts\deploy.bat
REM ─────────────────────────────────────────────────────────────────────────────

setlocal EnableDelayedExpansion

set PROJECT_DIR=%~dp0..
set BACKUP_DIR=%PROJECT_DIR%\storage\app\deploy-backups
set LOG_FILE=%PROJECT_DIR%\storage\logs\deploy.log

echo ═══════════════════════════════════════════════════════════════
echo  PGS Deployment Script
echo ═══════════════════════════════════════════════════════════════
echo.

REM ── Step 1: Pre-flight checks ──────────────────────────────────────────────
echo [1/8] Pre-flight checks...

php -v >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] PHP is not installed or not in PATH
    exit /b 1
)
echo   ✓ PHP found

node -v >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Node.js is not installed or not in PATH
    exit /b 1
)
echo   ✓ Node.js found

echo [1/8] Pre-flight checks passed
echo.

REM ── Step 2: Backup database ────────────────────────────────────────────────
echo [2/8] Backing up database...

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

set BACKUP_FILE=%BACKUP_DIR%\pre-deploy_%date:~-4%%date:~4,2%%date:~7,2%_%time:~0,2%%time:~3,2%.sql
set BACKUP_FILE=!BACKUP_FILE: =0!

mysqldump -u root pgs_app > "!BACKUP_FILE!" 2>NUL
if %ERRORLEVEL% NEQ 0 (
    echo [WARN] mysqldump failed - continuing without DB backup
) else (
    echo   ✓ Database backed up to !BACKUP_FILE!
)
echo.

REM ── Step 3: Backup .env ───────────────────────────────────────────────────
echo [3/8] Backing up .env...

if exist "%PROJECT_DIR%\.env" (
    copy /Y "%PROJECT_DIR%\.env" "%BACKUP_DIR%\.env.pre-deploy" >NUL
    echo   ✓ .env backed up
) else (
    echo   ⚠ No .env file found
)
echo.

REM ── Step 4: Install dependencies ──────────────────────────────────────────
echo [4/8] Installing dependencies...

cd /d "%PROJECT_DIR%"
call composer install --no-dev --optimize-autoloader --no-interaction
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] composer install failed
    exit /b 1
)
echo   ✓ Composer dependencies installed

call npm ci --no-audit
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] npm ci failed
    exit /b 1
)
echo   ✓ NPM dependencies installed
echo.

REM ── Step 5: Build assets ──────────────────────────────────────────────────
echo [5/8] Building frontend assets...

call npm run build
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Asset build failed
    exit /b 1
)
echo   ✓ Assets built
echo.

REM ── Step 6: Run migrations ────────────────────────────────────────────────
echo [6/8] Running migrations...

php artisan migrate --force
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Migration failed
    echo [ROLLBACK] Restoring .env...
    copy /Y "%BACKUP_DIR%\.env.pre-deploy" "%PROJECT_DIR%\.env" >NUL 2>&1
    exit /b 1
)
echo   ✓ Migrations applied
echo.

REM ── Step 7: Cache optimization ────────────────────────────────────────────
echo [7/8] Optimizing caches...

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:warm
echo   ✓ Caches warmed
echo.

REM ── Step 8: Restart services ──────────────────────────────────────────────
echo [8/8] Restarting services...

php artisan queue:restart
echo   ✓ Queue workers restarted
echo.

REM ── Health check ──────────────────────────────────────────────────────────
echo Running health check...
scripts\health-check.bat
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [WARN] Health check failed - application may need manual attention
    echo [ROLLBACK] To rollback: copy %BACKUP_DIR%\.env.pre-deploy to .env
    echo            and restore database from !BACKUP_FILE!
) else (
    echo.
    echo ═══════════════════════════════════════════════════════════════
    echo  Deployment complete!
    echo ═══════════════════════════════════════════════════════════════
)

echo.
echo [DEPLOY] Log: %LOG_FILE%
echo %date% %time% - Deployment completed >> "%LOG_FILE%" 2>NUL

exit /b 0
