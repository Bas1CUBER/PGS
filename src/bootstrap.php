<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

session_start();

define('PGS_ROOT', dirname(__DIR__));
define('PGS_SRC', PGS_ROOT . '/src');
define('PGS_TEMPLATES', PGS_ROOT . '/templates');

// Composer PSR-4 autoloader
require_once PGS_ROOT . '/vendor/autoload.php';

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

// Render a page using the master layout
function render_page(string $title, string $content, string $styles = '', string $scripts = ''): void {
    $pageTitle = $title;
    $pageStyles = $styles;
    $pageScripts = $scripts;
    require PGS_TEMPLATES . '/layout.php';
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

require_once PGS_SRC . '/Config/config.php';
require_once PGS_SRC . '/Database/db.php';
require_once PGS_SRC . '/Auth/access_guard.php';
require_once PGS_SRC . '/Notification/notification_helper.php';
