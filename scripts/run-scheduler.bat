@echo off
REM Runs every minute via Task Scheduler ("PGS Scheduler").
REM Executes all due Laravel scheduled tasks (backups, deadline expiry,
REM outbox prune, cache warm, heartbeats).
setlocal

set APP_DIR=%~dp0..\app
set PHP_EXE=C:\xampp\php\php.exe
if not exist "%PHP_EXE%" set PHP_EXE=php

cd /d "%APP_DIR%"
"%PHP_EXE%" artisan schedule:run >> storage\logs\scheduler.log 2>&1
