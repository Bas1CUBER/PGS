<?php
require_once __DIR__ . '/config.php';

// Single app-wide database connection (both drivers kept for BC).
// Written via $GLOBALS so connections are shared no matter which scope
// includes this file (page, function, CLI/PHPUnit bootstrap).

if (!isset($GLOBALS['pdo'])) {
    try {
        $GLOBALS['pdo'] = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $GLOBALS['pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

if (!isset($GLOBALS['conn'])) {
    $GLOBALS['conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($GLOBALS['conn']->connect_error) {
        die("Connection failed: " . $GLOBALS['conn']->connect_error);
    }
    $GLOBALS['conn']->set_charset('utf8mb4');
}
