<?php

declare(strict_types=1);

namespace PGS\Database;

use PDO;
use PDOException;
use mysqli;

class Database
{
    private static ?PDO $pdo = null;
    private static ?mysqli $mysqli = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS
                );
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function mysqli(): mysqli
    {
        if (self::$mysqli === null) {
            self::$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (self::$mysqli->connect_error) {
                die("Connection failed: " . self::$mysqli->connect_error);
            }
            self::$mysqli->set_charset('utf8mb4');
        }
        return self::$mysqli;
    }
}
