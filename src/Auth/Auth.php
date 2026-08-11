<?php

declare(strict_types=1);

namespace PGS\Auth;

use PDO;

class Auth
{
    public static function requirePageAccess(string $category): void
    {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
            header("Location: " . BASE_URL . "/login");
            exit();
        }
        if (($_SESSION['role'] ?? null) === 'admin') {
            return;
        }
        $map = [
            'roadmaps' => 'roadmaps',
            'scorecard' => 'scorecard',
            'performance_assessment' => 'performance_assessment',
            'cascading' => 'cascading',
            'governance' => 'governance'
        ];
        if (!isset($map[$category])) {
            return;
        }
        global $pdo;
        $allowed = 1;
        try {
            $stmt = $pdo->prepare("SELECT {$map[$category]} AS allowed FROM user_page_access WHERE user_id = :id");
            $stmt->execute([':id' => (int)$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $allowed = (int)$row['allowed'];
            }
        } catch (\Throwable $e) {
            $allowed = 1;
        }
        if ($allowed !== 1) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access Denied</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><div class="container" style="padding-top:110px;"><div class="alert alert-danger">You don\'t have access to this site. Contact administrator.</div><a class="btn btn-secondary" href="' . BASE_URL . '/employee_dashboard">Back to Dashboard</a></div></body></html>';
            exit();
        }
    }

    public static function isRoleFrozen(): bool
    {
        if (!isset($_SESSION['role']) || ($_SESSION['role'] === 'admin')) {
            return false;
        }
        try {
            global $pdo;
            $stmt = $pdo->prepare("CREATE TABLE IF NOT EXISTS deadline_controls (
              role ENUM('employee','focal') PRIMARY KEY,
              enabled TINYINT(1) NOT NULL DEFAULT 0,
              end_time DATETIME DEFAULT NULL,
              message VARCHAR(255) DEFAULT 'Please comply with the submission requirements before the deadline.',
              updated_by INT DEFAULT NULL,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $stmt->execute();
            $q = $pdo->prepare("SELECT enabled, end_time FROM deadline_controls WHERE role = :r");
            $q->execute([':r' => $_SESSION['role']]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            if ((int)$row['enabled'] !== 1) {
                return false;
            }
            if (!$row['end_time']) {
                return false;
            }
            $end = strtotime($row['end_time']);
            return ($end !== false && time() >= $end);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function requireNotFrozenForPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && self::isRoleFrozen()) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
            $isHtml = stripos($accept, 'text/html') !== false;
            $isMultipart = stripos($ctype, 'multipart/form-data') !== false;
            if ($isHtml || $isMultipart) {
                $_SESSION['flash_error'] = 'Submission window has closed. Please try again after the deadline is reset.';
                $back = $_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'];
                header('Location: '.$back);
                exit();
            } else {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false,'msg' => 'Submission window has closed for your role.']);
                exit();
            }
        }
    }
}
