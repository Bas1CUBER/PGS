<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

// Hardened session cookie (HttpOnly, SameSite=Lax). 'secure' only behind HTTPS in production.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('PGSSESSID');
session_start();

define('PGS_ROOT', dirname(__DIR__));
define('PGS_SRC', PGS_ROOT . '/src');
define('PGS_TEMPLATES', PGS_ROOT . '/templates');

// Composer PSR-4 autoloader
require_once PGS_ROOT . '/vendor/autoload.php';

// App configuration + shared helpers
require_once PGS_SRC . '/Config/config.php';
require_once PGS_SRC . '/helpers.php';
require_once PGS_SRC . '/Database/db.php';
require_once PGS_SRC . '/Auth/access_guard.php';
require_once PGS_SRC . '/Notification/notification_helper.php';
