@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  PGS Deploy Script
REM  Safe deployment with pre-flight checks, backup, and rollback.
REM  Usage: scripts\deploy.bat
REM ─────────────────────────────────────────────────────────────────────────────

setlocal EnableDelayedExpansion

set PROJECT_DIR=%~dp0..
REM Keep deploy backups OUTSIDE the web tree (htdocs): they contain .env
REM secrets and SQL dumps, and are only guarded by .htaccess otherwise.
set BACKUP_DIR=C:\xampp\backups\pgs\deploy
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

if exist "%~dp0db_credentials.bat" (
    call "%~dp0db_credentials.bat"
) else (
    echo [FAIL] Missing scripts\db_credentials.bat - create it with DB_BACKUP_USER / DB_BACKUP_PASS ^(ask the administrator^)
    exit /b 1
)

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%" 2>NUL
if not exist "%BACKUP_DIR%" (
    echo [FAIL] Could not create backup directory: %BACKUP_DIR%
    exit /b 1
)

REM Locale-independent timestamp (the %date% slice indexes break on other
REM regional formats).
for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmm"') do set TS=%%I
if "!TS!" == "" set TS=unknown

set BACKUP_FILE=%BACKUP_DIR%\pre-deploy_!TS!.sql

REM Pass credentials via a temp defaults-file so the password never appears
REM in the process command line (visible in task listings during the dump).
set "CNF_FILE=%TEMP%\pgs_mysqldump_%RANDOM%.cnf"
> "!CNF_FILE!" (
    echo [client]
    echo user=!DB_BACKUP_USER!
    echo password=!DB_BACKUP_PASS!
)

mysqldump --defaults-extra-file="!CNF_FILE!" pgs_app > "!BACKUP_FILE!" 2>NUL
set DUMP_RC=%ERRORLEVEL%
del "!CNF_FILE!" >NUL 2>&1

if !DUMP_RC! NEQ 0 (
    echo [FAIL] Database backup failed - ABORTING.
    echo        Deploying without a rollback point is not allowed; fix the
    echo        dump (credentials in scripts\db_credentials.bat^) and retry.
    if exist "!BACKUP_FILE!" del "!BACKUP_FILE!"
    exit /b 1
)
echo   ✓ Database backed up to !BACKUP_FILE!
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

REM ── Storage symlink (required for public/storage -> storage/app/public)
php artisan storage:link --force >NUL 2>&1
echo   ✓ Storage link ensured

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
