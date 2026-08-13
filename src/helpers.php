<?php

declare(strict_types=1);

/**
 * Global helpers used across PGS pages.
 * Loaded by src/bootstrap.php.
 */

// XSS-safe output helper
function h($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF token generation (stores in session)
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || (isset($_SESSION['_csrf_expires']) && $_SESSION['_csrf_expires'] < time())) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_expires'] = time() + 7200; // 2 hours
    }

    return $_SESSION['_csrf_token'];
}

// Render hidden CSRF input field
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

// Validate CSRF token from POST data
function verify_csrf(?string $token = null): bool
{
    $token ??= $_POST['_token'] ?? '';
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], $token) && (!isset($_SESSION['_csrf_expires']) || $_SESSION['_csrf_expires'] >= time());
}

// Flash message helpers
function flash(string $key): string
{
    $v = $_SESSION['flash_' . $key] ?? '';
    unset($_SESSION['flash_' . $key]);

    return $v;
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash_' . $key] = $message;
}

// Safe session read (returns default when the key is missing)
function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

// Cache-busted asset URL: /assets/<path>?v=<filemtime>
function asset(string $path): string
{
    $full = dirname(__DIR__) . '/assets/' . $path;
    $v = is_file($full) ? (string)filemtime($full) : '1';
    return BASE_URL . '/assets/' . $path . '?v=' . $v;
}
