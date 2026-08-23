@echo off
REM Runs every 5 minutes via Task Scheduler ("PGS Queue Worker").
REM Drains the database queue (e.g. RunBackupJob) then exits, so each tick
REM is a fresh process - no orphaned workers, no memory-leak concerns.
setlocal

set APP_DIR=%~dp0..\app
set PHP_EXE=C:\xampp\php\php.exe
if not exist "%PHP_EXE%" set PHP_EXE=php

cd /d "%APP_DIR%"
"%PHP_EXE%" artisan queue:work --stop-when-empty --sleep=3 --tries=3 --max-time=600 >> storage\logs\worker.log 2>&1
