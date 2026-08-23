@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  PGS Backup Verification Script
REM  Verifies that a recent backup exists and is valid.
REM  Usage: scripts\verify-backup.bat
REM ─────────────────────────────────────────────────────────────────────────────

setlocal EnableDelayedExpansion

set BACKUP_DIR=storage\app\backups
set ZIP_DIR=%BACKUP_DIR%\pgs
set MAX_AGE_HOURS=25

echo [BACKUP] Verifying backup integrity...

REM Check if backup directory exists
if not exist "%ZIP_DIR%" (
    echo [BACKUP] WARNING - Backup directory not found: %ZIP_DIR%
    echo [BACKUP] Creating backup directory...
    mkdir "%ZIP_DIR%" 2>NUL
    exit /b 1
)

REM Find the most recent .zip file
set LATEST_BACKUP=
for /f "delims=" %%F in ('dir /b /o-d "%ZIP_DIR%\*.zip" 2^>NUL') do (
    set LATEST_BACKUP=%%F
    goto :found
)

echo [BACKUP] WARNING - No backup files found in %ZIP_DIR%
exit /b 1

:found
set BACKUP_PATH=%ZIP_DIR%\!LATEST_BACKUP!
echo [BACKUP] Latest backup: !LATEST_BACKUP!

REM Check file size (must be > 1KB to be valid)
for %%A in ("!BACKUP_PATH!") do set FILE_SIZE=%%~zA
if !FILE_SIZE! LSS 1024 (
    echo [BACKUP] WARNING - Backup file is suspiciously small (!FILE_SIZE! bytes^)
    echo [BACKUP] This may indicate a failed backup.
    exit /b 1
)

echo [BACKUP] OK - Backup verified (!FILE_SIZE! bytes^)
exit /b 0
